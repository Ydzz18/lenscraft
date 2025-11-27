<?php
session_start();

// Include logger
require_once '../logger.php';

// Log the logout action before destroying session
if (isset($_SESSION['admin_id']) && isset($_SESSION['admin_username'])) {
    $logger = new UserLogger();
    $logger->log(
        UserLogger::ACTION_LOGOUT,
        "Admin '{$_SESSION['admin_username']}' logged out",
        null,
        $_SESSION['admin_id'],
        null,
        null,
        UserLogger::STATUS_SUCCESS
    );
}

// Destroy all session data
session_unset();
session_destroy();

// Redirect to admin login
header("Location: admin_login.php");
exit();
?>