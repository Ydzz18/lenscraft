<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Include database connection and logger
require_once 'db_connect.php';
require_once 'logger.php';

$logger = new UserLogger();

// Set page-specific variables
$page_title = 'Profile';
$page_css = 'profile.css';

// Get current user ID
$user_id = $_SESSION['user_id'];

// Function to get user info
function getUserInfo($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT id, username, email, created_at FROM users WHERE id = :user_id");
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Function to get user photos with like and comment counts
function getUserPhotos($pdo, $user_id) {
    $stmt = $pdo->prepare("
        SELECT 
            p.id, 
            p.title, 
            p.description, 
            p.image_path, 
            p.uploaded_at,
            COALESCE(l.like_count, 0) as like_count,
            COALESCE(c.comment_count, 0) as comment_count
        FROM photos p
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
        WHERE p.user_id = :user_id
        ORDER BY p.uploaded_at DESC
    ");
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to delete photo
function deletePhoto($pdo, $photo_id, $user_id) {
    // First verify the photo belongs to the user
    $stmt = $pdo->prepare("SELECT image_path, title FROM photos WHERE id = :photo_id AND user_id = :user_id");
    $stmt->bindParam(':photo_id', $photo_id);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $photo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Delete associated likes and comments first (cascade should handle this, but being safe)
        $pdo->prepare("DELETE FROM comments WHERE photo_id = :photo_id")->execute([':photo_id' => $photo_id]);
        $pdo->prepare("DELETE FROM likes WHERE photo_id = :photo_id")->execute([':photo_id' => $photo_id]);
        
        // Delete photo record
        $stmt = $pdo->prepare("DELETE FROM photos WHERE id = :photo_id");
        $stmt->bindParam(':photo_id', $photo_id);
        $stmt->execute();
        
        // Delete actual image file
        $image_path = 'uploads/' . $photo['image_path'];
        if (file_exists($image_path)) {
            unlink($image_path);
        }
        
        return ['success' => true, 'title' => $photo['title']];
    }
    return ['success' => false];
}

// Handle photo deletion
if (isset($_GET['delete_photo']) && is_numeric($_GET['delete_photo'])) {
    try {
        $pdo = getDBConnection();
        $delete_id = (int)$_GET['delete_photo'];
        
        $result = deletePhoto($pdo, $delete_id, $user_id);
        
        if ($result['success']) {
            $_SESSION['success'] = "Photo deleted successfully!";
            
            // Log successful deletion
            $logger->log(
                UserLogger::ACTION_DELETE_PHOTO,
                "User deleted their photo: '{$result['title']}' (ID: {$delete_id})",
                $user_id,
                null,
                'photos',
                $delete_id,
                UserLogger::STATUS_SUCCESS
            );
        } else {
            $_SESSION['error'] = "Photo not found or you don't have permission to delete it.";
            
            // Log failed deletion
            $logger->log(
                UserLogger::ACTION_DELETE_PHOTO,
                "Failed to delete photo - Not found or no permission (ID: {$delete_id})",
                $user_id,
                null,
                null,
                null,
                UserLogger::STATUS_FAILED
            );
        }
        
        header("Location: profile.php");
        exit();
    } catch(PDOException $e) {
        error_log("Delete photo error: " . $e->getMessage());
        $_SESSION['error'] = "Failed to delete photo. Please try again.";
        
        // Log error
        $logger->log(
            UserLogger::ACTION_DELETE_PHOTO,
            "Database error while deleting photo: " . $e->getMessage(),
            $user_id,
            null,
            null,
            null,
            UserLogger::STATUS_FAILED
        );
        
        header("Location: profile.php");
        exit();
    }
}

// Get user info and photos
try {
    $pdo = getDBConnection();
    
    $user_info = getUserInfo($pdo, $user_id);
    $user_photos = getUserPhotos($pdo, $user_id);
    
    if (!$user_info) {
        session_destroy();
        header("Location: login.php");
        exit();
    }
    
} catch(PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $_SESSION['error'] = "Failed to load profile. Please try again.";
    header("Location: index.php");
    exit();
}

// Handle session messages
$success_message = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$error_message = isset($_SESSION['error']) ? $_SESSION['error'] : '';

// Clear session messages
unset($_SESSION['success']);
unset($_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($user_info['username']); ?>'s Profile - LensCraft Photography</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Montserrat:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/profile.css">
    <link rel="icon" type="image/png" href="assets/img/Logo.png">
</head>
<body>
    <!-- Header -->
    <header>
        <div class="container">
            <nav class="navbar">
                <a href="home.php" class="logo">Lens<span>Craft</span></a>
                <div class="nav-links">
                    <a href="home.php">Gallery</a>
                    <a href="upload.php">Upload</a>
                    <a href="profile.php">Profile</a>
                    <a href="auth/logout.php">Logout</a>
                </div>
            </nav>
        </div>
    </header>

    <!-- Messages -->
    <?php if ($success_message): ?>
        <div class="messages">
            <div class="container">
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="messages">
            <div class="container">
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Profile Header -->
    <section class="profile-header">
        <div class="container">
            <div class="profile-avatar">
                <?php echo strtoupper(substr($user_info['username'], 0, 2)); ?>
            </div>
            <h1 class="profile-name"><?php echo htmlspecialchars($user_info['username']); ?></h1>
            <p class="profile-email"><?php echo htmlspecialchars($user_info['email']); ?></p>
            <p class="profile-joined">Member since <?php echo date('F Y', strtotime($user_info['created_at'])); ?></p>
        </div>
    </section>

    <!-- Profile Stats -->
    <div class="container">
        <div class="profile-stats">
            <div class="stat-item">
                <div class="stat-number"><?php echo count($user_photos); ?></div>
                <div class="stat-label">Photos</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">
                    <?php 
                    $total_likes = array_sum(array_column($user_photos, 'like_count'));
                    echo $total_likes;
                    ?>
                </div>
                <div class="stat-label">Total Likes</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">
                    <?php 
                    $total_comments = array_sum(array_column($user_photos, 'comment_count'));
                    echo $total_comments;
                    ?>
                </div>
                <div class="stat-label">Comments</div>
            </div>
        </div>
    </div>

    <!-- Photos Section -->
    <section class="photos-section">
        <div class="container">
            <h2 class="section-title">My Photos</h2>
            
            <?php if (empty($user_photos)): ?>
                <div class="no-photos">
                    <h3>You haven't uploaded any photos yet!</h3>
                    <p>Start sharing your photography with the community.</p>
                    <a href="upload.php" class="upload-link">
                        <i class="fas fa-cloud-upload-alt"></i> Upload Your First Photo
                    </a>
                </div>
            <?php else: ?>
                <div class="photos-grid">
                    <?php foreach ($user_photos as $photo): ?>
                        <div class="photo-card">
                            <div class="photo-image-container">
                                <img src="uploads/<?php echo htmlspecialchars($photo['image_path']); ?>" 
                                     alt="<?php echo htmlspecialchars($photo['title']); ?>" 
                                     class="photo-image">
                            </div>
                            <div class="photo-info">
                                <h3 class="photo-title"><?php echo htmlspecialchars($photo['title']); ?></h3>
                                <div class="photo-meta">
                                    <span><?php echo date('M j, Y', strtotime($photo['uploaded_at'])); ?></span>
                                    <span><?php echo strlen($photo['description']) > 50 ? substr(htmlspecialchars($photo['description']), 0, 47) . '...' : htmlspecialchars($photo['description']); ?></span>
                                </div>
                                <div class="photo-actions">
                                    <a href="like.php?id=<?php echo $photo['id']; ?>" class="action-btn">
                                        <i class="fas fa-heart"></i>
                                        <span><?php echo $photo['like_count']; ?></span>
                                    </a>
                                    <a href="profile.php?delete_photo=<?php echo $photo['id']; ?>" 
                                       class="action-btn delete-btn" 
                                       onclick="return confirm('Are you sure you want to delete this photo? This action cannot be undone.');">
                                        <i class="fas fa-trash"></i>
                                        <span>Delete</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="footer-bottom">
                <p>&copy; 2025 LensCraft Photography Community. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    window.scrollTo({
                        top: target.offsetTop - 80,
                        behavior: 'smooth'
                    });
                }
            });
        });
    </script>
</body>
</html>