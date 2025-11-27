<?php
// db_connect.php - Database connection configuration

// Database configuration
$host = 'localhost';
$dbname = 'photography_website';
$db_username = 'root'; // Default MySQL username (change if different)
$db_password = ''; // Default MySQL password (change if you set one)

/**
 * Get database connection
 * @return PDO Database connection object
 * @throws PDOException If connection fails
 */
function getDBConnection() {
    global $host, $dbname, $db_username, $db_password;
    
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $db_username, $db_password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new PDOException("Unable to connect to database. Please try again later.");
        }
    }
    
    return $pdo;
}

/**
 * Sanitize input data
 * @param string $data Input data to sanitize
 * @return string Sanitized data
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}
?>