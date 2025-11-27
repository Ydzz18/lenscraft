<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Please log in to comment on photos.";
    header("Location: auth/login.php");
    exit();
}

// Check if this is a POST request
if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: home.php");
    exit();
}

// Include database connection and logger
require_once 'db_connect.php';
require_once 'logger.php';

$logger = new UserLogger();

// Validate inputs
if (!isset($_POST['photo_id']) || !is_numeric($_POST['photo_id'])) {
    $_SESSION['error'] = "Invalid photo ID.";
    
    // Log failed attempt
    $logger->log(
        UserLogger::ACTION_COMMENT,
        "Failed comment attempt - Invalid photo ID",
        $_SESSION['user_id'],
        null,
        null,
        null,
        UserLogger::STATUS_FAILED
    );
    
    header("Location: home.php");
    exit();
}

if (!isset($_POST['comment']) || empty(trim($_POST['comment']))) {
    $_SESSION['error'] = "Comment cannot be empty.";
    
    // Log failed attempt
    $logger->log(
        UserLogger::ACTION_COMMENT,
        "Failed comment attempt - Empty comment",
        $_SESSION['user_id'],
        null,
        null,
        null,
        UserLogger::STATUS_FAILED
    );
    
    $redirect = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'home.php';
    header("Location: " . $redirect);
    exit();
}

$photo_id = (int)$_POST['photo_id'];
$user_id = $_SESSION['user_id'];
$comment = sanitizeInput($_POST['comment']);

// Validate comment length
if (strlen($comment) > 500) {
    $_SESSION['error'] = "Comment is too long (maximum 500 characters).";
    
    // Log warning
    $logger->log(
        UserLogger::ACTION_COMMENT,
        "Comment attempt exceeded length limit",
        $_SESSION['user_id'],
        null,
        null,
        null,
        UserLogger::STATUS_WARNING
    );
    
    $redirect = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'home.php';
    header("Location: " . $redirect);
    exit();
}

try {
    $pdo = getDBConnection();
    
    // Check if photo exists and get photo title
    $stmt = $pdo->prepare("SELECT p.id, p.title, u.username as photo_owner FROM photos p JOIN users u ON p.user_id = u.id WHERE p.id = :photo_id");
    $stmt->bindParam(':photo_id', $photo_id);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        $_SESSION['error'] = "Photo not found.";
        
        // Log failed attempt
        $logger->log(
            UserLogger::ACTION_COMMENT,
            "Failed comment attempt - Photo not found (ID: {$photo_id})",
            $_SESSION['user_id'],
            null,
            null,
            null,
            UserLogger::STATUS_FAILED
        );
        
        header("Location: home.php");
        exit();
    }
    
    $photo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Insert comment with correct column name: comment_text
    $stmt = $pdo->prepare("INSERT INTO comments (photo_id, user_id, comment_text, created_at) VALUES (:photo_id, :user_id, :comment_text, NOW())");
    $stmt->bindParam(':photo_id', $photo_id);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':comment_text', $comment);
    
    if ($stmt->execute()) {
        $comment_id = $pdo->lastInsertId();
        $_SESSION['success'] = "Comment posted successfully!";
        
        // Log successful comment
        $logger->log(
            UserLogger::ACTION_COMMENT,
            "Commented on photo '{$photo['title']}' by {$photo['photo_owner']}",
            $_SESSION['user_id'],
            null,
            'comments',
            $comment_id,
            UserLogger::STATUS_SUCCESS
        );
    } else {
        $_SESSION['error'] = "Failed to post comment. Please try again.";
        
        // Log failure
        $logger->log(
            UserLogger::ACTION_COMMENT,
            "Failed to insert comment on photo ID: {$photo_id}",
            $_SESSION['user_id'],
            null,
            null,
            null,
            UserLogger::STATUS_FAILED
        );
    }
    
} catch(PDOException $e) {
    error_log("Comment error: " . $e->getMessage());
    $_SESSION['error'] = "Failed to post comment. Please try again.";
    
    // Log error
    $logger->log(
        UserLogger::ACTION_COMMENT,
        "Database error while commenting: " . $e->getMessage(),
        $_SESSION['user_id'],
        null,
        null,
        null,
        UserLogger::STATUS_FAILED
    );
}

// Redirect back to the referring page or home
$redirect = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'home.php';
header("Location: " . $redirect);
exit();
?>