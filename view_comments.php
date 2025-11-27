<?php
session_start();

// Include database connection
require_once 'db_connect.php';

// Check if photo ID is provided
if (!isset($_GET['photo_id']) || !is_numeric($_GET['photo_id'])) {
    $_SESSION['error'] = "Invalid photo ID.";
    header("Location: home.php");
    exit();
}

$photo_id = (int)$_GET['photo_id'];

// Get photo details
try {
    $pdo = getDBConnection();
    
    // Get photo info
    $stmt = $pdo->prepare("
        SELECT p.id, p.title, p.image_path, p.description, u.username
        FROM photos p
        JOIN users u ON p.user_id = u.id
        WHERE p.id = :photo_id
    ");
    $stmt->bindParam(':photo_id', $photo_id);
    $stmt->execute();
    $photo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$photo) {
        $_SESSION['error'] = "Photo not found.";
        header("Location: home.php");
        exit();
    }
    
    // Get all comments
    $stmt = $pdo->prepare("
        SELECT c.id, c.comment_text, c.created_at, u.username, u.id as user_id
        FROM comments c
        JOIN users u ON c.user_id = u.id
        WHERE c.photo_id = :photo_id
        ORDER BY c.created_at DESC
    ");
    $stmt->bindParam(':photo_id', $photo_id);
    $stmt->execute();
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $_SESSION['error'] = "Failed to load comments.";
    header("Location: home.php");
    exit();
}

$page_title = 'Comments - ' . $photo['title'];
$page_css = 'home.css';

include 'header_logged_in.php';
?>

<style>
    .comments-container {
        max-width: 800px;
        margin: 100px auto 50px;
        padding: 20px;
    }
    
    .photo-preview {
        background: rgba(255, 255, 255, 0.05);
        border-radius: 15px;
        padding: 20px;
        margin-bottom: 30px;
    }
    
    .photo-preview img {
        width: 100%;
        max-height: 400px;
        object-fit: cover;
        border-radius: 10px;
        margin-bottom: 15px;
    }
    
    .photo-preview h2 {
        color: white;
        margin-bottom: 10px;
    }
    
    .photo-preview p {
        color: rgba(255, 255, 255, 0.7);
    }
    
    .comments-header {
        color: white;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid rgba(102, 126, 234, 0.3);
    }
    
    .back-link {
        display: inline-block;
        margin-bottom: 20px;
        color: #667eea;
        text-decoration: none;
        transition: all 0.3s ease;
    }
    
    .back-link:hover {
        color: #764ba2;
        transform: translateX(-5px);
    }
    
    .no-comments {
        text-align: center;
        color: rgba(255, 255, 255, 0.5);
        padding: 40px;
    }
</style>

<div class="comments-container">
    <a href="home.php" class="back-link">
        <i class="fas fa-arrow-left"></i> Back to Gallery
    </a>
    
    <div class="photo-preview">
        <img src="uploads/<?php echo htmlspecialchars($photo['image_path']); ?>" 
             alt="<?php echo htmlspecialchars($photo['title']); ?>">
        <h2><?php echo htmlspecialchars($photo['title']); ?></h2>
        <p>By <?php echo htmlspecialchars($photo['username']); ?></p>
        <?php if (!empty($photo['description'])): ?>
            <p><?php echo htmlspecialchars($photo['description']); ?></p>
        <?php endif; ?>
    </div>
    
    <div class="comments-header">
        <h3>Comments (<?php echo count($comments); ?>)</h3>
    </div>
    
    <?php if (empty($comments)): ?>
        <div class="no-comments">
            <i class="fas fa-comments" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.3;"></i>
            <p>No comments yet. Be the first to comment!</p>
        </div>
    <?php else: ?>
        <div class="comments-list">
            <?php foreach ($comments as $comment): ?>
                <div class="comment-item">
                    <strong><?php echo htmlspecialchars($comment['username']); ?>:</strong>
                    <span><?php echo htmlspecialchars($comment['comment_text']); ?></span>
                    <small class="comment-time">
                        <?php echo date('M j, Y g:i A', strtotime($comment['created_at'])); ?>
                    </small>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['user_id'])): ?>
        <form method="POST" action="comment.php" class="comment-form" style="display: block; margin-top: 30px;">
            <input type="hidden" name="photo_id" value="<?php echo $photo_id; ?>">
            <textarea name="comment" class="comment-input" placeholder="Add a comment..." maxlength="500" required></textarea>
            <button type="submit" class="comment-submit">Post Comment</button>
        </form>
    <?php endif; ?>
</div>

<?php
$page_scripts = '';
include 'footer_logged_in.php';
?>