<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in to like photos.']);
    exit();
}

// Include database connection
require_once 'db_connect.php';

// Check if photo ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid photo ID.']);
    exit();
}

$photo_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

try {
    $pdo = getDBConnection();
    
    // Check if photo exists
    $stmt = $pdo->prepare("SELECT id FROM photos WHERE id = :photo_id");
    $stmt->bindParam(':photo_id', $photo_id);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        echo json_encode(['success' => false, 'message' => 'Photo not found.']);
        exit();
    }
    
    // Check if user already liked this photo
    $stmt = $pdo->prepare("SELECT id FROM likes WHERE photo_id = :photo_id AND user_id = :user_id");
    $stmt->bindParam(':photo_id', $photo_id);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    
    $liked = false;
    $message = '';
    
    if ($stmt->rowCount() > 0) {
        // Unlike - remove the like
        $stmt = $pdo->prepare("DELETE FROM likes WHERE photo_id = :photo_id AND user_id = :user_id");
        $stmt->bindParam(':photo_id', $photo_id);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $message = "Photo unliked.";
        $liked = false;
    } else {
        // Like - add the like
        $stmt = $pdo->prepare("INSERT INTO likes (user_id, photo_id, created_at) VALUES (:user_id, :photo_id, NOW())");
        $stmt->bindParam(':photo_id', $photo_id);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $message = "Photo liked!";
        $liked = true;
    }
    
    // Get updated like count
    $stmt = $pdo->prepare("SELECT COUNT(*) as like_count FROM likes WHERE photo_id = :photo_id");
    $stmt->bindParam(':photo_id', $photo_id);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'liked' => $liked,
        'like_count' => $result['like_count']
    ]);
    
} catch(PDOException $e) {
    error_log("Like error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to process like. Please try again.']);
}
?>