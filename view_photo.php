<?php
session_start();

// Include database connection
require_once 'db_connect.php';

// Check if photo ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid photo ID']);
    exit();
}

$photo_id = (int)$_GET['id'];
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

try {
    $pdo = getDBConnection();
    
    // Get photo details with user info
    $stmt = $pdo->prepare("
        SELECT 
            p.id, 
            p.title, 
            p.description, 
            p.image_path, 
            p.uploaded_at,
            u.username,
            u.id as owner_id,
            COALESCE(l.like_count, 0) as like_count,
            COALESCE(c.comment_count, 0) as comment_count
        FROM photos p
        JOIN users u ON p.user_id = u.id
        LEFT JOIN (
            SELECT photo_id, COUNT(*) as like_count 
            FROM likes 
            GROUP BY photo_id
        ) l ON p.id = l.photo_id
        LEFT JOIN (
            SELECT photo_id, COUNT(*) as comment_count 
            FROM comments 
            GROUP BY photo_id
        ) c ON p.id = c.photo_id
        WHERE p.id = :photo_id
    ");
    $stmt->bindParam(':photo_id', $photo_id);
    $stmt->execute();
    $photo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$photo) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Photo not found']);
        exit();
    }
    
    // Check if user liked this photo
    $user_liked = false;
    if ($user_id) {
        $stmt = $pdo->prepare("SELECT id FROM likes WHERE photo_id = :photo_id AND user_id = :user_id");
        $stmt->bindParam(':photo_id', $photo_id);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $user_liked = $stmt->rowCount() > 0;
    }
    
    // Get comments
    $stmt = $pdo->prepare("
        SELECT 
            c.id,
            c.comment_text,
            c.created_at,
            u.username,
            u.id as user_id
        FROM comments c
        JOIN users u ON c.user_id = u.id
        WHERE c.photo_id = :photo_id
        ORDER BY c.created_at DESC
    ");
    $stmt->bindParam(':photo_id', $photo_id);
    $stmt->execute();
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'photo' => $photo,
        'user_liked' => $user_liked,
        'comments' => $comments,
        'is_logged_in' => ($user_id !== null)
    ]);
    
} catch(PDOException $e) {
    error_log("View photo error: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Failed to load photo']);
}
?>