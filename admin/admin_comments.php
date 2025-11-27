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

// Handle comment deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $comment_id = (int)$_GET['delete'];
    
    try {
        $pdo = getDBConnection();
        
        // Get comment info before deletion
        $stmt = $pdo->prepare("
            SELECT c.comment_text, u.username, p.title as photo_title 
            FROM comments c 
            JOIN users u ON c.user_id = u.id 
            JOIN photos p ON c.photo_id = p.id 
            WHERE c.id = :comment_id
        ");
        $stmt->bindParam(':comment_id', $comment_id);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $comment_info = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Delete comment
            $stmt = $pdo->prepare("DELETE FROM comments WHERE id = :comment_id");
            $stmt->bindParam(':comment_id', $comment_id);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Comment deleted successfully.";
                
                // Log admin action
                $logger->log(
                    UserLogger::ACTION_ADMIN_DELETE_COMMENT,
                    "Admin deleted comment by '{$comment_info['username']}' on photo '{$comment_info['photo_title']}' (Comment ID: {$comment_id})",
                    null,
                    $_SESSION['admin_id'],
                    'comments',
                    $comment_id,
                    UserLogger::STATUS_SUCCESS
                );
            } else {
                $_SESSION['error'] = "Failed to delete comment.";
                
                // Log failure
                $logger->log(
                    UserLogger::ACTION_ADMIN_DELETE_COMMENT,
                    "Failed to delete comment ID: {$comment_id}",
                    null,
                    $_SESSION['admin_id'],
                    null,
                    null,
                    UserLogger::STATUS_FAILED
                );
            }
        } else {
            $_SESSION['error'] = "Comment not found.";
            
            // Log not found
            $logger->log(
                UserLogger::ACTION_ADMIN_DELETE_COMMENT,
                "Comment not found for deletion (ID: {$comment_id})",
                null,
                $_SESSION['admin_id'],
                null,
                null,
                UserLogger::STATUS_FAILED
            );
        }
        
    } catch(PDOException $e) {
        error_log("Delete comment error: " . $e->getMessage());
        $_SESSION['error'] = "Failed to delete comment.";
        
        // Log error
        $logger->log(
            UserLogger::ACTION_ADMIN_DELETE_COMMENT,
            "Database error deleting comment: " . $e->getMessage(),
            null,
            $_SESSION['admin_id'],
            null,
            null,
            UserLogger::STATUS_FAILED
        );
    }
    
    header("Location: admin_comments.php");
    exit();
}

// Get all comments with user and photo info
try {
    $pdo = getDBConnection();
    
    $stmt = $pdo->query("
        SELECT 
            c.id,
            c.comment_text,
            c.created_at,
            u.username,
            p.title as photo_title,
            p.id as photo_id
        FROM comments c
        JOIN users u ON c.user_id = u.id
        JOIN photos p ON c.photo_id = p.id
        ORDER BY c.created_at DESC
    ");
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    error_log("Admin comments error: " . $e->getMessage());
    $_SESSION['error'] = "Failed to load comments.";
    $comments = [];
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
    <title>Comment Management - Admin Dashboard</title>
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
            <a href="admin_photos.php" class="nav-item">
                <i class="fas fa-images"></i>
                <span>Photos</span>
            </a>
            <a href="admin_comments.php" class="nav-item active">
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
            <h1>Comment Management</h1>
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
                <i class="fas fa-comments"></i> All Comments (<?php echo count($comments); ?>)
            </h2>
            
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Photo</th>
                            <th>Comment</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($comments as $comment): ?>
                            <tr>
                                <td><?php echo $comment['id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($comment['username']); ?></strong></td>
                                <td><?php echo htmlspecialchars($comment['photo_title']); ?></td>
                                <td class="comment-text">
                                    <?php 
                                    $text = htmlspecialchars($comment['comment_text']);
                                    echo strlen($text) > 100 ? substr($text, 0, 97) . '...' : $text;
                                    ?>
                                </td>
                                <td><?php echo date('M d, Y H:i', strtotime($comment['created_at'])); ?></td>
                                <td class="action-buttons">
                                    <a href="admin_comments.php?delete=<?php echo $comment['id']; ?>" 
                                       class="btn btn-small btn-danger"
                                       onclick="return confirm('Delete this comment? This cannot be undone.');">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if (empty($comments)): ?>
                <p style="text-align: center; padding: 40px; color: #999;">No comments found.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>