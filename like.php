<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Please log in to like photos.";
    header("Location: auth/login.php");
    exit();
}

// Include database connection and logger
require_once 'db_connect.php';
require_once 'logger.php';

$logger = new UserLogger();

// Check if photo ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid photo ID.";
    
    // Log failed attempt
    $logger->log(
        UserLogger::ACTION_LIKE_PHOTO,
        "Failed like attempt - Invalid photo ID",
        $_SESSION['user_id'],
        null,
        null,
        null,
        UserLogger::STATUS_FAILED
    );
    
    header("Location: home.php");
    exit();
}

$photo_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

try {
    $pdo = getDBConnection();
    
    // Check if photo exists and get photo info
    $stmt = $pdo->prepare("SELECT p.id, p.title, u.username as photo_owner FROM photos p JOIN users u ON p.user_id = u.id WHERE p.id = :photo_id");
    $stmt->bindParam(':photo_id', $photo_id);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        $_SESSION['error'] = "Photo not found.";
        
        // Log failed attempt
        $logger->log(
            UserLogger::ACTION_LIKE_PHOTO,
            "Failed like attempt - Photo not found (ID: {$photo_id})",
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
    
    // Check if user already liked this photo
    $stmt = $pdo->prepare("SELECT id FROM likes WHERE photo_id = :photo_id AND user_id = :user_id");
    $stmt->bindParam(':photo_id', $photo_id);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        // Unlike - remove the like
        $like_id = $stmt->fetch(PDO::FETCH_ASSOC)['id'];
        
        $stmt = $pdo->prepare("DELETE FROM likes WHERE photo_id = :photo_id AND user_id = :user_id");
        $stmt->bindParam(':photo_id', $photo_id);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        $_SESSION['success'] = "Photo unliked.";
        
        // Log unlike action
        $logger->log(
            UserLogger::ACTION_UNLIKE_PHOTO,
            "Unliked photo '{$photo['title']}' by {$photo['photo_owner']}",
            $_SESSION['user_id'],
            null,
            'likes',
            $photo_id,
            UserLogger::STATUS_SUCCESS
        );
    } else {
        // Like - add the like
        $stmt = $pdo->prepare("INSERT INTO likes (user_id, photo_id, created_at) VALUES (:user_id, :photo_id, NOW())");
        $stmt->bindParam(':photo_id', $photo_id);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        $like_id = $pdo->lastInsertId();
        
        $_SESSION['success'] = "Photo liked!";
        
        // Log like action
        $logger->log(
            UserLogger::ACTION_LIKE_PHOTO,
            "Liked photo '{$photo['title']}' by {$photo['photo_owner']}",
            $_SESSION['user_id'],
            null,
            'likes',
            $like_id,
            UserLogger::STATUS_SUCCESS
        );
    }
    
} catch(PDOException $e) {
    error_log("Like error: " . $e->getMessage());
    $_SESSION['error'] = "Failed to process like. Please try again.";
    
    // Log error
    $logger->log(
        UserLogger::ACTION_LIKE_PHOTO,
        "Database error while liking photo: " . $e->getMessage(),
        $_SESSION['user_id'],
        null,
        null,
        null,
        UserLogger::STATUS_FAILED
    );
}

// Redirect back to the referring page with anchor to maintain position
$redirect = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'home.php';

// Add anchor to scroll back to the photo
if (strpos($redirect, '#') === false) {
    $redirect .= '#photo-' . $photo_id;
}

header("Location: " . $redirect);
exit();
?>