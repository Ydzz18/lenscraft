<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['is_admin'])) {
    header("Location: admin_login.php");
    exit();
}

require_once '../db_connect.php';

// Get dashboard statistics
try {
    $pdo = getDBConnection();
    
    // Total users
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
    $total_users = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total admins
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM admins");
    $total_admins = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total photos
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM photos");
    $total_photos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total likes
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM likes");
    $total_likes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total comments
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM comments");
    $total_comments = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Recent users (last 5)
    $stmt = $pdo->query("SELECT id, username, email, created_at FROM users ORDER BY created_at DESC LIMIT 5");
    $recent_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Recent photos (last 5)
    $stmt = $pdo->query("
        SELECT p.id, p.title, p.uploaded_at, u.username 
        FROM photos p 
        JOIN users u ON p.user_id = u.id 
        ORDER BY p.uploaded_at DESC 
        LIMIT 5
    ");
    $recent_photos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Most active users (by photo count)
    $stmt = $pdo->query("
        SELECT u.username, COUNT(p.id) as photo_count 
        FROM users u 
        LEFT JOIN photos p ON u.id = p.user_id 
        GROUP BY u.id 
        ORDER BY photo_count DESC 
        LIMIT 5
    ");
    $active_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    error_log("Admin dashboard error: " . $e->getMessage());
    $error = "Failed to load dashboard data.";
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
    <title>Admin Dashboard - LensCraft Photography</title>
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
            <a href="admin_dashboard.php" class="nav-item active">
                <i class="fas fa-chart-line"></i>
                <span>Dashboard</span>
            </a>
            <a href="admin_users.php" class="nav-item">
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
            <h1>Admin Dashboard</h1>
            <div class="admin-info">
                <i class="fas fa-user-shield"></i>
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
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

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon users">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-details">
                    <h3><?php echo $total_users; ?></h3>
                    <p>Total Users</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon admins">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="stat-details">
                    <h3><?php echo $total_admins; ?></h3>
                    <p>Total Admins</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon photos">
                    <i class="fas fa-images"></i>
                </div>
                <div class="stat-details">
                    <h3><?php echo $total_photos; ?></h3>
                    <p>Total Photos</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon likes">
                    <i class="fas fa-heart"></i>
                </div>
                <div class="stat-details">
                    <h3><?php echo $total_likes; ?></h3>
                    <p>Total Likes</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon comments">
                    <i class="fas fa-comments"></i>
                </div>
                <div class="stat-details">
                    <h3><?php echo $total_comments; ?></h3>
                    <p>Total Comments</p>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="content-grid">
            <!-- Recent Users -->
            <div class="card">
                <h2 class="card-title">
                    <i class="fas fa-user-plus"></i> Recent Users
                </h2>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Joined</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_users as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <a href="admin_users.php" class="view-all">View All Users <i class="fas fa-arrow-right"></i></a>
            </div>

            <!-- Recent Photos -->
            <div class="card">
                <h2 class="card-title">
                    <i class="fas fa-images"></i> Recent Photos
                </h2>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Uploaded By</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_photos as $photo): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($photo['title']); ?></td>
                                    <td><?php echo htmlspecialchars($photo['username']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($photo['uploaded_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <a href="admin_photos.php" class="view-all">View All Photos <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>

        <!-- Most Active Users -->
        <div class="card">
            <h2 class="card-title">
                <i class="fas fa-fire"></i> Most Active Users
            </h2>
            <div class="activity-list">
                <?php foreach ($active_users as $user): ?>
                    <div class="activity-item">
                        <div class="activity-avatar">
                            <?php echo strtoupper(substr($user['username'], 0, 2)); ?>
                        </div>
                        <div class="activity-details">
                            <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                            <span><?php echo $user['photo_count']; ?> photos uploaded</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</body>
</html>