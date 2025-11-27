<?php
/**
 * Admin Directory Index
 * Automatically redirects to appropriate page based on authentication status
 */
session_start();

// Check if user is logged in and is admin
if (isset($_SESSION['admin_id']) && isset($_SESSION['is_admin'])) {
    // Already authenticated as admin - go to dashboard
    header("Location: admin_dashboard.php");
    exit();
} else {
    // Not authenticated - go to login
    header("Location: admin_login.php");
    exit();
}
?>