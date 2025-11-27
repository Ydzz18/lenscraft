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
$page_title = 'Upload Photo';
$page_css = 'upload.css';

// Handle photo upload
$upload_dir = 'uploads/';
$upload_errors = [];
$success_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate title
    $title = sanitizeInput($_POST['title']);
    if (empty($title)) {
        $upload_errors[] = "Photo title is required.";
    } elseif (strlen($title) > 100) {
        $upload_errors[] = "Photo title is too long (max 100 characters).";
    }
    
    // Validate description
    $description = sanitizeInput($_POST['description']);
    if (strlen($description) > 500) {
        $upload_errors[] = "Description is too long (max 500 characters).";
    }
    
    // Handle file upload
    if (empty($_FILES['photo']['name'])) {
        $upload_errors[] = "Please select a photo to upload.";
    } else {
        $file_name = $_FILES['photo']['name'];
        $file_tmp = $_FILES['photo']['tmp_name'];
        $file_size = $_FILES['photo']['size'];
        $file_error = $_FILES['photo']['error'];
        
        // Get file extension
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (!in_array($file_ext, $allowed_ext)) {
            $upload_errors[] = "Invalid file type. Only JPG, JPEG, PNG, GIF, and WebP files are allowed.";
        }
        
        if ($file_size > 10000000) { // 10MB limit
            $upload_errors[] = "File size too large. Maximum file size is 10MB.";
        }
        
        if ($file_error !== UPLOAD_ERR_OK) {
            $upload_errors[] = "File upload error occurred.";
        }
    }
    
    // If no errors, proceed with upload
    if (empty($upload_errors)) {
        try {
            // Create uploads directory if it doesn't exist
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Generate unique filename
            $new_file_name = uniqid() . '_' . time() . '.' . $file_ext;
            $upload_path = $upload_dir . $new_file_name;
            
            // Move uploaded file
            if (move_uploaded_file($file_tmp, $upload_path)) {
                // Connect to database
                $pdo = getDBConnection();
                
                // Insert photo record
                $stmt = $pdo->prepare("INSERT INTO photos (user_id, title, description, image_path, uploaded_at) VALUES (:user_id, :title, :description, :image_path, NOW())");
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                $stmt->bindParam(':title', $title);
                $stmt->bindParam(':description', $description);
                $stmt->bindParam(':image_path', $new_file_name);
                
                if ($stmt->execute()) {
                    $photo_id = $pdo->lastInsertId();
                    $success_message = "Photo uploaded successfully!";
                    
                    // Log successful upload
                    $logger->log(
                        UserLogger::ACTION_UPLOAD_PHOTO,
                        "Uploaded photo: '{$title}' (File: {$new_file_name})",
                        $_SESSION['user_id'],
                        null,
                        'photos',
                        $photo_id,
                        UserLogger::STATUS_SUCCESS
                    );
                    
                    // Clear form data
                    $title = '';
                    $description = '';
                } else {
                    $upload_errors[] = "Failed to save photo to database.";
                    
                    // Log failure
                    $logger->log(
                        UserLogger::ACTION_UPLOAD_PHOTO,
                        "Failed to save photo to database: {$title}",
                        $_SESSION['user_id'],
                        null,
                        null,
                        null,
                        UserLogger::STATUS_FAILED
                    );
                    
                    // Clean up uploaded file
                    unlink($upload_path);
                }
            } else {
                $upload_errors[] = "Failed to move uploaded file.";
                
                // Log failure
                $logger->log(
                    UserLogger::ACTION_UPLOAD_PHOTO,
                    "Failed to move uploaded file",
                    $_SESSION['user_id'],
                    null,
                    null,
                    null,
                    UserLogger::STATUS_FAILED
                );
            }
        } catch(PDOException $e) {
            $upload_errors[] = "Database error: " . $e->getMessage();
            
            // Log error
            $logger->log(
                UserLogger::ACTION_UPLOAD_PHOTO,
                "Database error during upload: " . $e->getMessage(),
                $_SESSION['user_id'],
                null,
                null,
                null,
                UserLogger::STATUS_FAILED
            );
            
            // Clean up uploaded file if it exists
            if (isset($upload_path) && file_exists($upload_path)) {
                unlink($upload_path);
            }
        }
    } else {
        // Log validation errors
        $logger->log(
            UserLogger::ACTION_UPLOAD_PHOTO,
            "Upload validation failed: " . implode(', ', $upload_errors),
            $_SESSION['user_id'],
            null,
            null,
            null,
            UserLogger::STATUS_WARNING
        );
    }
}

// Set default values for form
$title = isset($_POST['title']) ? sanitizeInput($_POST['title']) : '';
$description = isset($_POST['description']) ? sanitizeInput($_POST['description']) : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Photo - LensCraft Photography</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Montserrat:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/upload.css">
    <link rel="icon" type="image/png" href="assets/img/Logo.png">
</head>
<body>
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

    <div class="container">
        <div class="upload-container">
            <h1 class="upload-title">Upload Your Photo</h1>
            
            <?php if ($success_message): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($upload_errors)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <ul class="error-list">
                        <?php foreach ($upload_errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="photo-title">Photo Title *</label>
                    <input type="text" id="photo-title" name="title" required 
                           value="<?php echo htmlspecialchars($title); ?>"
                           placeholder="Give your photo a descriptive title">
                </div>
                
                <div class="form-group">
                    <label for="photo-description">Description</label>
                    <textarea id="photo-description" name="description" 
                              placeholder="Describe your photo, location, camera settings, or story behind the shot..."><?php echo htmlspecialchars($description); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="photo-file">Choose Photo *</label>
                    <div class="file-input">
                        <span class="file-input-label">
                            <i class="fas fa-cloud-upload-alt"></i> Click to select photo (JPG, PNG, GIF, WebP - Max 10MB)
                        </span>
                        <input type="file" id="photo-file" name="photo" accept="image/*" required>
                    </div>
                </div>
                
                <button type="submit" class="submit-btn">Upload Photo</button>
            </form>
            
            <a href="home.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Gallery
            </a>
        </div>
    </div>

    <script>
        // Display selected file name
        document.getElementById('photo-file').addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name;
            if (fileName) {
                const label = document.querySelector('.file-input-label');
                label.innerHTML = '<i class="fas fa-file-image"></i> ' + 
                    (fileName.length > 30 ? fileName.substring(0, 27) + '...' : fileName);
            }
        });
    </script>
</body>
</html>