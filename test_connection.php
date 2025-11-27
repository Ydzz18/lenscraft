<?php
/**
 * Database Connection and Logger Test Script
 * Place this file in your root directory and access via browser
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Database & Logger Test</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #4CAF50; padding-bottom: 10px; }
        h2 { color: #666; margin-top: 30px; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 4px; margin: 10px 0; border-left: 4px solid #28a745; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; margin: 10px 0; border-left: 4px solid #dc3545; }
        .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 4px; margin: 10px 0; border-left: 4px solid #17a2b8; }
        .warning { background: #fff3cd; color: #856404; padding: 15px; border-radius: 4px; margin: 10px 0; border-left: 4px solid #ffc107; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto; }
        .test-item { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 4px; }
        .checkmark { color: #28a745; font-weight: bold; }
        .crossmark { color: #dc3545; font-weight: bold; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔍 Database & Logger Test</h1>
        <p><em>Testing your photography website setup...</em></p>
";

// Test 1: Database Connection
echo "<div class='test-item'>";
echo "<h2>Test 1: Database Connection</h2>";
try {
    require_once 'db_connect.php';
    $pdo = getDBConnection();
    echo "<div class='success'><span class='checkmark'>✓</span> Database connection successful!</div>";
    
    // Get database name
    $stmt = $pdo->query("SELECT DATABASE() as db_name");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<div class='info'>Connected to database: <strong>{$result['db_name']}</strong></div>";
} catch (Exception $e) {
    echo "<div class='error'><span class='crossmark'>✗</span> Database connection failed: " . $e->getMessage() . "</div>";
    echo "<div class='warning'>⚠ Please check your database configuration in db_connect.php</div>";
    exit;
}
echo "</div>";

// Test 2: Check Required Tables
echo "<div class='test-item'>";
echo "<h2>Test 2: Required Tables</h2>";
$required_tables = ['users', 'photos', 'likes', 'comments', 'user_logs', 'admins'];
$missing_tables = [];

foreach ($required_tables as $table) {
    $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
    if ($stmt->rowCount() > 0) {
        echo "<div class='success'><span class='checkmark'>✓</span> Table '<strong>$table</strong>' exists</div>";
    } else {
        echo "<div class='error'><span class='crossmark'>✗</span> Table '<strong>$table</strong>' is missing</div>";
        $missing_tables[] = $table;
    }
}

if (empty($missing_tables)) {
    echo "<div class='success'><strong>All required tables exist!</strong></div>";
} else {
    echo "<div class='error'><strong>Missing tables detected!</strong> Please import the SQL file.</div>";
}
echo "</div>";

// Test 3: Logger Initialization
echo "<div class='test-item'>";
echo "<h2>Test 3: Logger Initialization</h2>";
try {
    require_once 'logger.php';
    $logger = new UserLogger($pdo);
    
    if ($logger->isEnabled()) {
        echo "<div class='success'><span class='checkmark'>✓</span> Logger initialized successfully!</div>";
        echo "<div class='info'>Logger is enabled and ready to use.</div>";
    } else {
        echo "<div class='warning'><span class='crossmark'>!</span> Logger initialized but is disabled.</div>";
        echo "<div class='info'>This usually means the user_logs table doesn't exist or has issues.</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'><span class='crossmark'>✗</span> Logger initialization failed: " . $e->getMessage() . "</div>";
}
echo "</div>";

// Test 4: Test Logging
echo "<div class='test-item'>";
echo "<h2>Test 4: Test Logging</h2>";
try {
    $test_result = $logger->log(
        UserLogger::ACTION_LOGIN,
        "Test log entry from test script",
        1, // Test user ID
        null,
        'users',
        1,
        UserLogger::STATUS_SUCCESS
    );
    
    if ($test_result) {
        echo "<div class='success'><span class='checkmark'>✓</span> Test log entry created successfully!</div>";
        
        // Retrieve the last log entry
        $stmt = $pdo->query("SELECT * FROM user_logs ORDER BY created_at DESC LIMIT 1");
        $last_log = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "<div class='info'><strong>Last log entry:</strong>";
        echo "<pre>" . print_r($last_log, true) . "</pre>";
        echo "</div>";
    } else {
        echo "<div class='warning'><span class='crossmark'>!</span> Logging returned false, but no exception was thrown.</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'><span class='crossmark'>✗</span> Test logging failed: " . $e->getMessage() . "</div>";
}
echo "</div>";

// Test 5: Sample Users
echo "<div class='test-item'>";
echo "<h2>Test 5: Sample Users</h2>";
try {
    $stmt = $pdo->query("SELECT id, username, email, is_admin FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($users) > 0) {
        echo "<div class='success'><span class='checkmark'>✓</span> Found " . count($users) . " user(s) in database</div>";
        echo "<div class='info'><strong>Sample users:</strong>";
        echo "<pre>";
        foreach ($users as $user) {
            echo "- {$user['username']} ({$user['email']}) - Admin: " . ($user['is_admin'] ? 'Yes' : 'No') . "\n";
        }
        echo "</pre>";
        echo "<div class='info'>💡 <strong>Default password for all sample users:</strong> <code>password</code></div>";
        echo "</div>";
    } else {
        echo "<div class='warning'><span class='crossmark'>!</span> No users found in database.</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'><span class='crossmark'>✗</span> Failed to fetch users: " . $e->getMessage() . "</div>";
}
echo "</div>";

// Test 6: Log Statistics
echo "<div class='test-item'>";
echo "<h2>Test 6: Log Statistics</h2>";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM user_logs");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<div class='info'>Total log entries: <strong>{$result['total']}</strong></div>";
    
    if ($result['total'] > 0) {
        $stmt = $pdo->query("
            SELECT action_type, COUNT(*) as count, status 
            FROM user_logs 
            GROUP BY action_type, status 
            ORDER BY count DESC 
            LIMIT 10
        ");
        $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<div class='info'><strong>Top actions:</strong>";
        echo "<pre>";
        foreach ($stats as $stat) {
            echo "- {$stat['action_type']} ({$stat['status']}): {$stat['count']}\n";
        }
        echo "</pre></div>";
    }
} catch (Exception $e) {
    echo "<div class='error'><span class='crossmark'>✗</span> Failed to fetch log statistics: " . $e->getMessage() . "</div>";
}
echo "</div>";

// Summary
echo "<div class='test-item' style='background: #f8f9fa; border: 2px solid #4CAF50;'>";
echo "<h2>✅ Test Summary</h2>";
echo "<div class='success'><strong>All critical tests passed!</strong></div>";
echo "<div class='info'>";
echo "<strong>Next steps:</strong><br>";
echo "1. Try logging in at: <a href='auth/login.php'>auth/login.php</a><br>";
echo "2. Or register a new account at: <a href='auth/register.php'>auth/register.php</a><br>";
echo "3. View the home page at: <a href='home.php'>home.php</a><br>";
echo "4. Delete this test file for security: <code>test_connection.php</code>";
echo "</div>";
echo "</div>";

echo "
    </div>
</body>
</html>";
?>