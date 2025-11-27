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

// Handle user deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $user_id = (int)$_GET['delete'];
    
    try {
        $pdo = getDBConnection();
        
        // Get user info before deletion
        $stmt = $pdo->prepare("SELECT username, email FROM users WHERE id = :user_id");
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get user's photos for file deletion
            $stmt = $pdo->prepare("SELECT image_path FROM photos WHERE user_id = :user_id");
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->execute();
            $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Delete user (cascade will handle related records)
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = :user_id");
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                // Delete photo files
                foreach ($photos as $photo) {
                    $file_path = '../uploads/' . $photo['image_path'];
                    if (file_exists($file_path)) {
                        unlink($file_path);
                    }
                }
                
                $_SESSION['success'] = "User deleted successfully.";
                
                // Log admin action
                $logger->log(
                    UserLogger::ACTION_ADMIN_DELETE_USER,
                    "Admin deleted user '{$user['username']}' ({$user['email']}) - ID: {$user_id}",
                    null,
                    $_SESSION['admin_id'],
                    'users',
                    $user_id,
                    UserLogger::STATUS_SUCCESS
                );
            } else {
                $_SESSION['error'] = "Failed to delete user.";
                
                $logger->log(
                    UserLogger::ACTION_ADMIN_DELETE_USER,
                    "Failed to delete user ID: {$user_id}",
                    null,
                    $_SESSION['admin_id'],
                    null,
                    null,
                    UserLogger::STATUS_FAILED
                );
            }
        } else {
            $_SESSION['error'] = "User not found.";
        }
        
    } catch(PDOException $e) {
        error_log("Delete user error: " . $e->getMessage());
        $_SESSION['error'] = "Failed to delete user: " . $e->getMessage();
        
        $logger->log(
            UserLogger::ACTION_ADMIN_DELETE_USER,
            "Database error while deleting user ID {$user_id}: " . $e->getMessage(),
            null,
            $_SESSION['admin_id'],
            null,
            null,
            UserLogger::STATUS_FAILED
        );
    }
    
    header("Location: admin_users.php");
    exit();
}

// Handle toggle admin status
if (isset($_GET['toggle_admin']) && is_numeric($_GET['toggle_admin'])) {
    $user_id = (int)$_GET['toggle_admin'];
    
    try {
        $pdo = getDBConnection();
        
        // Get current status
        $stmt = $pdo->prepare("SELECT username, is_admin FROM users WHERE id = :user_id");
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Toggle admin status
        $stmt = $pdo->prepare("UPDATE users SET is_admin = NOT is_admin WHERE id = :user_id");
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            $new_status = $user['is_admin'] ? 'User' : 'Admin';
            $_SESSION['success'] = "User admin status updated.";
            
            // Log admin action
            $logger->log(
                UserLogger::ACTION_ADMIN_TOGGLE_STATUS,
                "Admin toggled admin status for '{$user['username']}' to: {$new_status}",
                null,
                $_SESSION['admin_id'],
                'users',
                $user_id,
                UserLogger::STATUS_SUCCESS
            );
        } else {
            $_SESSION['error'] = "Failed to update user status.";
            
            $logger->log(
                UserLogger::ACTION_ADMIN_TOGGLE_STATUS,
                "Failed to toggle admin status for user ID: {$user_id}",
                null,
                $_SESSION['admin_id'],
                null,
                null,
                UserLogger::STATUS_FAILED
            );
        }
        
    } catch(PDOException $e) {
        error_log("Toggle admin error: " . $e->getMessage());
        $_SESSION['error'] = "Failed to update user status: " . $e->getMessage();
        
        $logger->log(
            UserLogger::ACTION_ADMIN_TOGGLE_STATUS,
            "Database error toggling admin status: " . $e->getMessage(),
            null,
            $_SESSION['admin_id'],
            null,
            null,
            UserLogger::STATUS_FAILED
        );
    }
    
    header("Location: admin_users.php");
    exit();
}

// Get all users with photo counts
$users = [];
$error_details = '';
try {
    $pdo = getDBConnection();
    
    $stmt = $pdo->query("
        SELECT 
            u.id, 
            u.username, 
            u.email, 
            u.is_admin, 
            u.created_at,
            COUNT(p.id) as photo_count
        FROM users u
        LEFT JOIN photos p ON u.id = p.user_id
        GROUP BY u.id, u.username, u.email, u.is_admin, u.created_at
        ORDER BY u.created_at DESC
    ");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    error_log("Admin users error: " . $e->getMessage());
    $error_details = $e->getMessage();
    $_SESSION['error'] = "Failed to load users. Error: " . $e->getMessage();
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
    <title>User Management - Admin Dashboard</title>
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
            <a href="admin_users.php" class="nav-item active">
                <i class="fas fa-users"></i>
                <span>Users</span>
            </a>
            <a href="admin_admins.php" class="nav-item">
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
            <h1>User Management</h1>
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

        <div class="card">
            <h2 class="card-title">
                <i class="fas fa-users"></i> All Users (<?php echo count($users); ?>)
            </h2>
            
            <?php if (empty($users) && !$error_details): ?>
                <div style="text-align: center; padding: 60px 20px; color: #999;">
                    <i class="fas fa-users" style="font-size: 48px; margin-bottom: 20px; opacity: 0.3;"></i>
                    <h3 style="color: #666; margin-bottom: 10px;">No Users Found</h3>
                    <p>There are currently no users in the system.</p>
                </div>
            <?php elseif (!empty($users)): ?>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Photos</th>
                                <th>Role</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['id']); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo htmlspecialchars($user['photo_count']); ?></td>
                                    <td>
                                        <?php if ($user['is_admin']): ?>
                                            <span class="badge badge-admin">Admin</span>
                                        <?php else: ?>
                                            <span class="badge badge-user">User</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                    <td class="action-buttons">
                                        <a href="admin_users.php?toggle_admin=<?php echo $user['id']; ?>" 
                                           class="btn btn-small btn-warning"
                                           onclick="return confirm('Toggle admin status for this user?');">
                                            <i class="fas fa-user-shield"></i>
                                        </a>
                                        <a href="admin_users.php?delete=<?php echo $user['id']; ?>" 
                                           class="btn btn-small btn-danger"
                                           onclick="return confirm('Delete this user and all their photos? This cannot be undone.');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>