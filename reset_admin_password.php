<?php
/**
 * Admin Password Reset Script
 * Use this to reset or create admin accounts
 * DELETE THIS FILE AFTER USE!
 */

require_once 'db_connect.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Admin Password Reset</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 { color: #333; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .warning { background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin: 15px 0; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #667eea; color: white; }
        form { margin: 20px 0; }
        input[type='text'], input[type='password'], input[type='email'] {
            width: 100%;
            padding: 10px;
            margin: 5px 0 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        button {
            background: #667eea;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        button:hover { background: #5568d3; }
        .delete-btn {
            background: #dc3545;
            margin-left: 10px;
        }
        .delete-btn:hover { background: #c82333; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>🔐 Admin Password Reset Tool</h1>";

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['reset_password'])) {
        $admin_id = (int)$_POST['admin_id'];
        $new_password = $_POST['new_password'];
        
        if (strlen($new_password) < 6) {
            echo "<div class='error'>❌ Password must be at least 6 characters long.</div>";
        } else {
            try {
                $pdo = getDBConnection();
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("UPDATE admins SET password = :password WHERE id = :id");
                $stmt->execute([
                    ':password' => $hashed_password,
                    ':id' => $admin_id
                ]);
                
                echo "<div class='success'>✅ Password updated successfully for admin ID: {$admin_id}</div>";
                echo "<div class='info'><strong>New password:</strong> {$new_password}</div>";
            } catch(PDOException $e) {
                echo "<div class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }
    }
    
    // Handle create new admin
    if (isset($_POST['create_admin'])) {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        
        if (empty($username) || empty($email) || empty($password)) {
            echo "<div class='error'>❌ All fields are required.</div>";
        } elseif (strlen($password) < 6) {
            echo "<div class='error'>❌ Password must be at least 6 characters long.</div>";
        } else {
            try {
                $pdo = getDBConnection();
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("INSERT INTO admins (username, email, password) VALUES (:username, :email, :password)");
                $stmt->execute([
                    ':username' => $username,
                    ':email' => $email,
                    ':password' => $hashed_password
                ]);
                
                echo "<div class='success'>✅ New admin created successfully!</div>";
                echo "<div class='info'><strong>Username:</strong> {$username}<br><strong>Password:</strong> {$password}</div>";
            } catch(PDOException $e) {
                echo "<div class='error'>❌ Error: Username or email already exists.</div>";
            }
        }
    }
}

// Display existing admins
try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT id, username, email, created_at, last_login FROM admins ORDER BY id");
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>📋 Existing Admin Accounts</h2>";
    
    if (empty($admins)) {
        echo "<div class='warning'>⚠️ No admin accounts found!</div>";
    } else {
        echo "<table>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Created</th>
                    <th>Last Login</th>
                    <th>Action</th>
                </tr>";
        
        foreach ($admins as $admin) {
            echo "<tr>
                    <td>{$admin['id']}</td>
                    <td><strong>{$admin['username']}</strong></td>
                    <td>{$admin['email']}</td>
                    <td>" . date('M d, Y', strtotime($admin['created_at'])) . "</td>
                    <td>" . ($admin['last_login'] ? date('M d, Y H:i', strtotime($admin['last_login'])) : 'Never') . "</td>
                    <td>
                        <form method='POST' style='display:inline;'>
                            <input type='hidden' name='admin_id' value='{$admin['id']}'>
                            <input type='password' name='new_password' placeholder='New password' style='width:150px; display:inline;' required>
                            <button type='submit' name='reset_password'>Reset Password</button>
                        </form>
                    </td>
                  </tr>";
        }
        
        echo "</table>";
    }
    
} catch(PDOException $e) {
    echo "<div class='error'>❌ Database error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

// Create new admin form
echo "<h2>➕ Create New Admin Account</h2>";
echo "<form method='POST'>
        <label><strong>Username:</strong></label>
        <input type='text' name='username' required>
        
        <label><strong>Email:</strong></label>
        <input type='email' name='email' required>
        
        <label><strong>Password:</strong></label>
        <input type='password' name='password' required>
        
        <button type='submit' name='create_admin'>Create Admin</button>
      </form>";

echo "<div class='warning' style='margin-top: 30px;'>
        <strong>⚠️ SECURITY WARNING:</strong><br>
        This file can create and reset admin passwords. Delete it immediately after use!<br>
        <strong>File to delete:</strong> reset_admin_password.php
      </div>";

echo "<div class='info'>
        <strong>💡 Quick Actions:</strong><br>
        1. Reset password for existing admin using the table above<br>
        2. Or create a new admin account using the form<br>
        3. Test login at: <a href='admin/admin_login.php'>admin/admin_login.php</a><br>
        4. <strong style='color: red;'>DELETE THIS FILE</strong> after successful login
      </div>";

echo "</div></body></html>";
?>