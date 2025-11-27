<?php
session_start();

// Log the logout action before destroying session
if (isset($_SESSION['user_id']) && isset($_SESSION['username'])) {
    // Try to log the logout (but don't fail if logger doesn't work)
    try {
        require_once '../db_connect.php';
        require_once '../logger.php';
        
        $pdo = getDBConnection();
        $logger = new UserLogger($pdo);
        $logger->log(
            UserLogger::ACTION_LOGOUT,
            "User '{$_SESSION['username']}' logged out",
            $_SESSION['user_id'],
            null,
            null,
            null,
            UserLogger::STATUS_SUCCESS
        );
    } catch (Exception $e) {
        error_log("Logger error during logout: " . $e->getMessage());
    }
}

// Destroy all session data
session_unset();
session_destroy();

// Redirect to home
header("Location: ../index.php");
exit();
?>