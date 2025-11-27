<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['is_admin'])) {
    header("Location: admin_login.php");
    exit();
}

require_once '../db_connect.php';
require_once '../logger.php';
$logger = new UserLogger();

// Handle admin deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $admin_id = (int)$_GET['delete'];
    
    // Prevent deleting yourself
    if ($admin_id == $_SESSION['admin_id']) {
        $_SESSION['error'] = "You cannot delete your own account.";
    } else {
        try {
            $pdo = getDBConnection();
            
            // Check if this is the last admin
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM admins");
            $total_admins = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            if ($total_admins <= 1) {
                $_SESSION['error'] = "Cannot delete the last admin account.";
            } else {
                // Get admin info before deletion
                $stmt = $pdo->prepare("SELECT username FROM admins WHERE id = :admin_id");
                $stmt->bindParam(':admin_id', $admin_id);
                $stmt->execute();
                $admin_to_delete = $stmt->fetch(PDO::FETCH_ASSOC);
                $admin_username = $admin_to_delete['username'];
                
                // Delete admin
                $stmt = $pdo->prepare("DELETE FROM admins WHERE id = :admin_id");
                $stmt->bindParam(':admin_id', $admin_id);
                
                if ($stmt->execute()) {
                    $_SESSION['success'] = "Admin deleted successfully.";
                    
                    // Log admin deletion
                    $logger->log(
                        UserLogger::ACTION_ADMIN_DELETE_ADMIN,
                        "Admin '{$_SESSION['admin_username']}' deleted admin account '{$admin_username}' (ID: {$admin_id})",
                        null,
                        $_SESSION['admin_id'],
                        'admins',
                        $admin_id,
                        UserLogger::STATUS_SUCCESS
                    );
                } else {
                    $_SESSION['error'] = "Failed to delete admin.";
                    
                    // Log failure
                    $logger->log(
                        UserLogger::ACTION_ADMIN_DELETE_ADMIN,
                        "Failed to delete admin ID: {$admin_id}",
                        null,
                        $_SESSION['admin_id'],
                        null,
                        null,
                        UserLogger::STATUS_FAILED
                    );
                }
            }
            
        } catch(PDOException $e) {
            error_log("Delete admin error: " . $e->getMessage());
            $_SESSION['error'] = "Failed to delete admin.";
            
            // Log error
            $logger->log(
                UserLogger::ACTION_ADMIN_DELETE_ADMIN,
                "Database error deleting admin: " . $e->getMessage(),
                null,
                $_SESSION['admin_id'],
                null,
                null,
                UserLogger::STATUS_FAILED
            );
        }
    }
    
    header("Location: admin_admins.php");
    exit();
}

// Handle add new admin
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_admin'])) {
    $username = sanitizeInput($_POST['username']);
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    $errors = [];
    
    // Validation
    if (empty($username)) {
        $errors[] = "Username is required.";
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email is required.";
    }
    if (empty($password)) {
        $errors[] = "Password is required.";
    }
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }
    if (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters.";
    }
    
    if (empty($errors)) {
        try {
            $pdo = getDBConnection();
            
            // Check if username or email already exists
            $stmt = $pdo->prepare("SELECT id FROM admins WHERE username = :username OR email = :email");
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $_SESSION['error'] = "Username or email already exists.";
                
                // Log failed attempt
                $logger->log(
                    UserLogger::ACTION_ADMIN_ADD_ADMIN,
                    "Failed to create admin - username/email already exists: {$username}",
                    null,
                    $_SESSION['admin_id'],
                    null,
                    null,
                    UserLogger::STATUS_FAILED
                );
            } else {
                // Hash password and insert
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("INSERT INTO admins (username, email, password) VALUES (:username, :email, :password)");
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':password', $hashed_password);
                
                if ($stmt->execute()) {
                    $new_admin_id = $pdo->lastInsertId();
                    $_SESSION['success'] = "New admin added successfully.";
                    
                    // Log admin creation
                    $logger->log(
                        UserLogger::ACTION_ADMIN_ADD_ADMIN,
                        "Admin '{$_SESSION['admin_username']}' created new admin account: '{$username}'",
                        null,
                        $_SESSION['admin_id'],
                        'admins',
                        $new_admin_id,
                        UserLogger::STATUS_SUCCESS
                    );
                } else {
                    $_SESSION['error'] = "Failed to add admin.";
                    
                    // Log failure
                    $logger->log(
                        UserLogger::ACTION_ADMIN_ADD_ADMIN,
                        "Failed to create admin account: {$username}",
                        null,
                        $_SESSION['admin_id'],
                        null,
                        null,
                        UserLogger::STATUS_FAILED
                    );
                }
            }
            
        } catch(PDOException $e) {
            error_log("Add admin error: " . $e->getMessage());
            $_SESSION['error'] = "Failed to add admin.";
            
            // Log error
            $logger->log(
                UserLogger::ACTION_ADMIN_ADD_ADMIN,
                "Database error creating admin: " . $e->getMessage(),
                null,
                $_SESSION['admin_id'],
                null,
                null,
                UserLogger::STATUS_FAILED
            );
        }
    } else {
        $_SESSION['error'] = implode(' ', $errors);
    }
    
    header("Location: admin_admins.php");
    exit();
}

// Get all admins
try {
    $pdo = getDBConnection();
    
    $stmt = $pdo->query("
        SELECT id, username, email, created_at, last_login
        FROM admins
        ORDER BY created_at DESC
    ");
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    error_log("Admin list error: " . $e->getMessage());
    $_SESSION['error'] = "Failed to load admins.";
    $admins = [];
}

// Handle session messages
$success_message = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$error_message = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['success']);
unset($_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Management - Admin Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Montserrat:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="icon" type="image/png" href="assets/img/admin.png">
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo">
            <i class="fas fa-camera"></i>
            <span>Lens<strong>Craft</strong></span>
        </div>
        
        <nav class="sidebar-nav">
            <a href="admin_dashboard.php" class="nav-item">
                <i class="fas fa-chart-line"></i>
                <span>Dashboard</span>
            </a>
            <a href="admin_users.php" class="nav-item">
                <i class="fas fa-users"></i>
                <span>Users</span>
            </a>
            <a href="admin_admins.php" class="nav-item active">
                <i class="fas fa-user-shield"></i>
                <span>Admins</span>
            </a>
            <a href="admin_photos.php" class="nav-item">
                <i class="fas fa-images"></i>
                <span>Photos</span>
            </a>
            <a href="admin_comments.php" class="nav-item">
                <i class="fas fa-comments"></i>
                <span>Comments</span>
            </a>
            <a href="admin_logs.php" class="nav-item">
                <i class="fas fa-history"></i>
                <span>Activity Logs</span>
            </a>
            <a href="../home.php" class="nav-item">
                <i class="fas fa-home"></i>
                <span>View Site</span>
            </a>
            <a href="admin_logout.php" class="nav-item logout">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <header class="top-bar">
            <h1>Admin Management</h1>
            <div class="admin-info">
                <i class="fas fa-user-shield"></i>
                <span><?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
            </div>
        </header>

        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- Add New Admin Form -->
        <div class="card">
            <h2 class="card-title">
                <i class="fas fa-user-plus"></i> Add New Admin
            </h2>
            <form method="POST" action="" class="admin-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" required placeholder="Enter username">
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" required placeholder="Enter email">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required placeholder="Enter password">
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required placeholder="Confirm password">
                    </div>
                </div>
                <button type="submit" name="add_admin" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add Admin
                </button>
            </form>
        </div>

        <!-- Admins List -->
        <div class="card">
            <h2 class="card-title">
                <i class="fas fa-user-shield"></i> All Admins (<?php echo count($admins); ?>)
            </h2>
            
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Created</th>
                            <th>Last Login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($admins as $admin): ?>
                            <tr>
                                <td><?php echo $admin['id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($admin['username']); ?></strong>
                                    <?php if ($admin['id'] == $_SESSION['admin_id']): ?>
                                        <span class="badge badge-info">You</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($admin['email']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($admin['created_at'])); ?></td>
                                <td>
                                    <?php 
                                    if ($admin['last_login']) {
                                        echo date('M d, Y H:i', strtotime($admin['last_login']));
                                    } else {
                                        echo '<span class="text-muted">Never</span>';
                                    }
                                    ?>
                                </td>
                                <td class="action-buttons">
                                    <?php if ($admin['id'] != $_SESSION['admin_id']): ?>
                                        <a href="admin_admins.php?delete=<?php echo $admin['id']; ?>" 
                                           class="btn btn-small btn-danger"
                                           onclick="return confirm('Delete this admin account? This cannot be undone.');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>