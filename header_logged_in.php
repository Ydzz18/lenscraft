<?php
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>LensCraft Photography</title>
    <link rel="icon" type="image/png" href="assets/img/Logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Montserrat:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php if (isset($page_css)): ?>
        <link rel="stylesheet" href="assets/css/<?php echo $page_css; ?>">
    <?php endif; ?>
    <?php if (isset($additional_css)): ?>
        <link rel="stylesheet" href="assets/css/<?php echo $additional_css; ?>">
    <?php endif; ?>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="container">
            <nav class="navbar">
                <a href="home.php" class="logo">Lens<span>Craft</span></a>
                <div class="nav-links">
                    <a href="home.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'home.php') ? 'class="active"' : ''; ?>>Gallery</a>
                    <a href="upload.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'upload.php') ? 'class="active"' : ''; ?>>Upload</a>
                    <a href="profile.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'profile.php') ? 'class="active"' : ''; ?>>Profile</a>
                    <a href="auth/logout.php">Logout</a>
                </div>
            </nav>
        </div>
    </header>