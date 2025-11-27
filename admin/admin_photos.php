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

// Handle photo deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $photo_id = (int)$_GET['delete'];
    
    try {
        $pdo = getDBConnection();
        
        // Get photo info
        $stmt = $pdo->prepare("SELECT p.image_path, p.title, u.username FROM photos p JOIN users u ON p.user_id = u.id WHERE p.id = :photo_id");
        $stmt->bindParam(':photo_id', $photo_id);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $photo = $stmt->fetch(PDO::FETCH_ASSOC);
            $photo_title = $photo['title'];
            $photo_owner = $photo['username'];
            
            // Delete photo record
            $stmt = $pdo->prepare("DELETE FROM photos WHERE id = :photo_id");
            $stmt->bindParam(':photo_id', $photo_id);
            
            if ($stmt->execute()) {
                // Delete file
                $file_path = '../uploads/' . $photo['image_path'];
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
                
                $_SESSION['success'] = "Photo deleted successfully.";
                
                // Log admin action
                $logger->log(
                    UserLogger::ACTION_ADMIN_DELETE_PHOTO,
                    "Admin '{$_SESSION['admin_username']}' deleted photo '{$photo_title}' by '{$photo_owner}' (ID: {$photo_id})",
                    null,
                    $_SESSION['admin_id'],
                    'photos',
                    $photo_id,
                    UserLogger::STATUS_SUCCESS
                );
            } else {
                $_SESSION['error'] = "Failed to delete photo.";
                
                // Log failure
                $logger->log(
                    UserLogger::ACTION_ADMIN_DELETE_PHOTO,
                    "Failed to delete photo ID: {$photo_id}",
                    null,
                    $_SESSION['admin_id'],
                    null,
                    null,
                    UserLogger::STATUS_FAILED
                );
            }
        } else {
            $_SESSION['error'] = "Photo not found.";
            
            // Log not found
            $logger->log(
                UserLogger::ACTION_ADMIN_DELETE_PHOTO,
                "Photo not found for deletion (ID: {$photo_id})",
                null,
                $_SESSION['admin_id'],
                null,
                null,
                UserLogger::STATUS_FAILED
            );
        }
        
    } catch(PDOException $e) {
        error_log("Delete photo error: " . $e->getMessage());
        $_SESSION['error'] = "Failed to delete photo.";
        
        // Log error
        $logger->log(
            UserLogger::ACTION_ADMIN_DELETE_PHOTO,
            "Database error deleting photo ID {$photo_id}: " . $e->getMessage(),
            null,
            $_SESSION['admin_id'],
            null,
            null,
            UserLogger::STATUS_FAILED
        );
    }
    
    header("Location: admin_photos.php");
    exit();
}

// Get all photos with user info and stats
try {
    $pdo = getDBConnection();
    
    $stmt = $pdo->query("
        SELECT 
            p.id, 
            p.title, 
            p.description,
            p.image_path, 
            p.uploaded_at,
            u.username,
            COUNT(DISTINCT l.id) as like_count,
            COUNT(DISTINCT c.id) as comment_count
        FROM photos p
        JOIN users u ON p.user_id = u.id
        LEFT JOIN likes l ON p.id = l.photo_id
        LEFT JOIN comments c ON p.id = c.photo_id
        GROUP BY p.id
        ORDER BY p.uploaded_at DESC
    ");
    $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    error_log("Admin photos error: " . $e->getMessage());
    $_SESSION['error'] = "Failed to load photos.";
    $photos = [];
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
    <title>Photo Management - Admin Dashboard</title>
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
            <a href="admin_admins.php" class="nav-item">
                <i class="fas fa-user-shield"></i>
                <span>Admins</span>
            </a>
            <a href="admin_photos.php" class="nav-item active">
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
            <h1>Photo Management</h1>
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
                <i class="fas fa-images"></i> All Photos (<?php echo count($photos); ?>)
            </h2>
            
            <div class="photos-gallery">
                <?php foreach ($photos as $photo): ?>
                    <div class="admin-photo-card">
                        <div class="admin-photo-image">
                            <img src="../uploads/<?php echo htmlspecialchars($photo['image_path']); ?>" 
                                 alt="<?php echo htmlspecialchars($photo['title']); ?>">
                        </div>
                        <div class="admin-photo-info">
                            <h3><?php echo htmlspecialchars($photo['title']); ?></h3>
                            <p class="photo-author">By: <?php echo htmlspecialchars($photo['username']); ?></p>
                            <p class="photo-description">
                                <?php 
                                $desc = htmlspecialchars($photo['description']);
                                echo strlen($desc) > 80 ? substr($desc, 0, 77) . '...' : $desc; 
                                ?>
                            </p>
                            <div class="photo-stats">
                                <span><i class="fas fa-heart"></i> <?php echo $photo['like_count']; ?></span>
                                <span><i class="fas fa-comment"></i> <?php echo $photo['comment_count']; ?></span>
                            </div>
                            <p class="photo-date">
                                <i class="fas fa-clock"></i> 
                                <?php echo date('M d, Y', strtotime($photo['uploaded_at'])); ?>
                            </p>
                            <div class="photo-actions">
                                <a href="admin_photos.php?delete=<?php echo $photo['id']; ?>" 
                                   class="btn btn-small btn-danger"
                                   onclick="return confirm('Delete this photo? This cannot be undone.');">
                                    <i class="fas fa-trash"></i> Delete
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (empty($photos)): ?>
                <p style="text-align: center; padding: 40px; color: #999;">No photos found.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>