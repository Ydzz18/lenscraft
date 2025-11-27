<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in to comment on photos.']);
    exit();
}

// Check if this is a POST request
if ($_SERVER["REQUEST_METHOD"] != "POST") {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

// Include database connection
require_once 'db_connect.php';

// Validate inputs
if (!isset($_POST['photo_id']) || !is_numeric($_POST['photo_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid photo ID.']);
    exit();
}

if (!isset($_POST['comment']) || empty(trim($_POST['comment']))) {
    echo json_encode(['success' => false, 'message' => 'Comment cannot be empty.']);
    exit();
}

$photo_id = (int)$_POST['photo_id'];
$user_id = $_SESSION['user_id'];
$comment = sanitizeInput($_POST['comment']);

// Validate comment length
if (strlen($comment) > 500) {
    echo json_encode(['success' => false, 'message' => 'Comment is too long (maximum 500 characters).']);
    exit();
}

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
    
    // Get current username
    $stmt = $pdo->prepare("SELECT username FROM users WHERE id = :user_id");
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Insert comment with correct column name: comment_text
    $stmt = $pdo->prepare("INSERT INTO comments (photo_id, user_id, comment_text, created_at) VALUES (:photo_id, :user_id, :comment_text, NOW())");
    $stmt->bindParam(':photo_id', $photo_id);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':comment_text', $comment);
    
    if ($stmt->execute()) {
        // Get updated comment count
        $stmt = $pdo->prepare("SELECT COUNT(*) as comment_count FROM comments WHERE photo_id = :photo_id");
        $stmt->bindParam(':photo_id', $photo_id);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'message' => 'Comment posted successfully!',
            'comment' => htmlspecialchars($comment),
            'username' => htmlspecialchars($user['username']),
            'comment_count' => $result['comment_count']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to post comment. Please try again.']);
    }
    
} catch(PDOException $e) {
    error_log("Comment error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to post comment. Please try again.']);
}
?>