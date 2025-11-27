<?php
session_start();

// If already logged in as admin, redirect to dashboard
if (isset($_SESSION['admin_id'])) {
    header("Location: admin_dashboard.php");
    exit();
}

// Include database connection and logger
require_once '../db_connect.php';
require_once '../logger.php';

$logger = new UserLogger();
$login_errors = [];
$login_attempt = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $login_attempt = true;
    
    // Validate username
    $username = sanitizeInput($_POST['username']);
    if (empty($username)) {
        $login_errors[] = "Username is required.";
    }
    
    // Validate password
    $password = $_POST['password'];
    if (empty($password)) {
        $login_errors[] = "Password is required.";
    }
    
    // If no errors, attempt login
    if (empty($login_errors)) {
        try {
            $pdo = getDBConnection();
            
            // Check if admin exists in admins table
            $stmt = $pdo->prepare("SELECT id, username, password, email FROM admins WHERE username = :username");
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            
            if ($stmt->rowCount() == 1) {
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Verify password
                if (password_verify($password, $admin['password'])) {
                    // Set admin session variables
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_username'] = $admin['username'];
                    $_SESSION['admin_email'] = $admin['email'];
                    $_SESSION['is_admin'] = true;
                    
                    // Update last login
                    $updateStmt = $pdo->prepare("UPDATE admins SET last_login = NOW() WHERE id = :id");
                    $updateStmt->bindParam(':id', $admin['id']);
                    $updateStmt->execute();
                    
                    // Log successful admin login
                    $logger->log(
                        UserLogger::ACTION_ADMIN_LOGIN,
                        "Admin '{$admin['username']}' logged in successfully",
                        null,
                        $admin['id'],
                        'admins',
                        $admin['id'],
                        UserLogger::STATUS_SUCCESS
                    );
                    
                    // Redirect to admin dashboard
                    header("Location: admin_dashboard.php");
                    exit();
                } else {
                    $login_errors[] = "Invalid username or password.";
                    
                    // Log failed login attempt
                    $logger->log(
                        UserLogger::ACTION_FAILED_LOGIN,
                        "Failed admin login attempt - Invalid password for username: {$username}",
                        null,
                        null,
                        null,
                        null,
                        UserLogger::STATUS_FAILED
                    );
                }
            } else {
                $login_errors[] = "Invalid username or password.";
                
                // Log failed login attempt
                $logger->log(
                    UserLogger::ACTION_FAILED_LOGIN,
                    "Failed admin login attempt - Username not found: {$username}",
                    null,
                    null,
                    null,
                    null,
                    UserLogger::STATUS_FAILED
                );
            }
            
        } catch(PDOException $e) {
            error_log("Admin login error: " . $e->getMessage());
            $login_errors[] = "Login failed. Please try again.";
            
            // Log database error
            $logger->log(
                UserLogger::ACTION_FAILED_LOGIN,
                "Admin login database error: " . $e->getMessage(),
                null,
                null,
                null,
                null,
                UserLogger::STATUS_FAILED
            );
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - LensCraft Photography</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Montserrat:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/auth.css">
    <link rel="icon" type="image/png" href="assets/img/admin.png">
</head>
<body>
    <div class="login-container">
        <a href="../index.php" class="logo">Lens<span>Craft</span></a>
        <h2 class="login-title"><i class="fas fa-shield-alt"></i> Admin Login</h2>
        
        <?php if (!empty($login_errors)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <ul class="error-list">
                    <?php foreach ($login_errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <div class="form-group">
                <label for="username"><i class="fas fa-user"></i> Username</label>
                <input type="text" id="username" name="username" required 
                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                       placeholder="Enter admin username">
            </div>
            
            <div class="form-group">
                <label for="password"><i class="fas fa-lock"></i> Password</label>
                <input type="password" id="password" name="password" required placeholder="Enter password">
            </div>
            
            <button type="submit" class="submit-btn">
                <i class="fas fa-sign-in-alt"></i> Login to Admin Panel
            </button>
        </form>
        
        <div class="login-footer">
            <a href="../index.php"><i class="fas fa-arrow-left"></i> Back to Home</a>
        </div>
    </div>
</body>
</html>