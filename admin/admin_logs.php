<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['is_admin'])) {
    header("Location: admin_login.php");
    exit();
}

require_once '../db_connect.php';
require_once '../logger.php';

$logger = new UserLogger();

// Pagination settings
$per_page = 50;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $per_page;

// Filters
$filters = [];
if (isset($_GET['action_type']) && !empty($_GET['action_type'])) {
    $filters['action_type'] = $_GET['action_type'];
}
if (isset($_GET['status']) && !empty($_GET['status'])) {
    $filters['status'] = $_GET['status'];
}
if (isset($_GET['user_id']) && is_numeric($_GET['user_id'])) {
    $filters['user_id'] = (int)$_GET['user_id'];
}
if (isset($_GET['date_from']) && !empty($_GET['date_from'])) {
    $filters['date_from'] = $_GET['date_from'] . ' 00:00:00';
}
if (isset($_GET['date_to']) && !empty($_GET['date_to'])) {
    $filters['date_to'] = $_GET['date_to'] . ' 23:59:59';
}

// Get logs and count
$logs = $logger->getAllLogs($filters, $per_page, $offset);
$total_logs = $logger->getLogsCount($filters);
$total_pages = ceil($total_logs / $per_page);

// Get all users for filter dropdown
try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT id, username FROM users ORDER BY username");
    $all_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $all_users = [];
}

// Get activity statistics
$stats = $logger->getActivityStats(null, 7); // Last 7 days

// Handle session messages
$success_message = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$error_message = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['success']);
unset($_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Activity Logs - Admin Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Montserrat:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="icon" type="image/png" href="assets/img/admin.png">
    <style>
        .filters-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .filters-form .form-group {
            margin: 0;
        }
        
        .filters-form label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 5px;
            color: #333;
        }
        
        .filters-form select,
        .filters-form input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .filters-actions {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }
        
        .filters-actions button {
            padding: 8px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
        }
        
        .btn-filter {
            background: #007bff;
            color: white;
        }
        
        .btn-reset {
            background: #6c757d;
            color: white;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-success {
            background: #d4edda;
            color: #155724;
        }
        
        .status-failed {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .action-badge {
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            background: #e9ecef;
            color: #495057;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 30px;
            padding: 20px 0;
        }
        
        .pagination a,
        .pagination span {
            padding: 8px 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #333;
        }
        
        .pagination a:hover {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }
        
        .pagination .current {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .stat-box {
            background: white;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #007bff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .stat-box h4 {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #666;
            text-transform: uppercase;
        }
        
        .stat-box .count {
            font-size: 32px;
            font-weight: bold;
            color: #333;
        }
        
        .log-description {
            max-width: 400px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .ip-address {
            font-family: monospace;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo">
            <i class="fas fa-camera"></i>
            <span>Lens<strong>Craft</strong></span>
        </div>
        
        <nav class="sidebar-nav">
            <a href="admin_dashboard.php" class="nav-item">
                <i class="fas fa-chart-line"></i>
                <span>Dashboard</span>
            </a>
            <a href="admin_users.php" class="nav-item">
                <i class="fas fa-users"></i>
                <span>Users</span>
            </a>
            <a href="admin_admins.php" class="nav-item">
                <i class="fas fa-user-shield"></i>
                <span>Admins</span>
            </a>
            <a href="admin_photos.php" class="nav-item">
                <i class="fas fa-images"></i>
                <span>Photos</span>
            </a>
            <a href="admin_comments.php" class="nav-item">
                <i class="fas fa-comments"></i>
                <span>Comments</span>
            </a>
            <a href="admin_logs.php" class="nav-item active">
                <i class="fas fa-history"></i>
                <span>Activity Logs</span>
            </a>
            <a href="../home.php" class="nav-item">
                <i class="fas fa-home"></i>
                <span>View Site</span>
            </a>
            <a href="admin_logout.php" class="nav-item logout">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <header class="top-bar">
            <h1>User Activity Logs</h1>
            <div class="admin-info">
                <i class="fas fa-user-shield"></i>
                <span><?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
            </div>
        </header>

        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- Activity Statistics -->
        <div class="card">
            <h2 class="card-title">
                <i class="fas fa-chart-bar"></i> Activity Overview (Last 7 Days)
            </h2>
            <div class="stats-grid">
                <?php
                $action_counts = [];
                foreach ($stats as $stat) {
                    if (!isset($action_counts[$stat['action_type']])) {
                        $action_counts[$stat['action_type']] = 0;
                    }
                    $action_counts[$stat['action_type']] += $stat['count'];
                }
                
                $top_actions = array_slice($action_counts, 0, 5, true);
                foreach ($top_actions as $action => $count):
                ?>
                <div class="stat-box">
                    <h4><?php echo ucwords(str_replace('_', ' ', $action)); ?></h4>
                    <div class="count"><?php echo $count; ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Filters -->
        <div class="card">
            <h2 class="card-title">
                <i class="fas fa-filter"></i> Filter Logs
            </h2>
            <form method="GET" action="" class="filters-form">
                <div class="form-group">
                    <label for="action_type">Action Type</label>
                    <select name="action_type" id="action_type">
                        <option value="">All Actions</option>
                        <option value="login" <?php echo (isset($filters['action_type']) && $filters['action_type'] == 'login') ? 'selected' : ''; ?>>Login</option>
                        <option value="logout" <?php echo (isset($filters['action_type']) && $filters['action_type'] == 'logout') ? 'selected' : ''; ?>>Logout</option>
                        <option value="register" <?php echo (isset($filters['action_type']) && $filters['action_type'] == 'register') ? 'selected' : ''; ?>>Register</option>
                        <option value="upload_photo" <?php echo (isset($filters['action_type']) && $filters['action_type'] == 'upload_photo') ? 'selected' : ''; ?>>Upload Photo</option>
                        <option value="delete_photo" <?php echo (isset($filters['action_type']) && $filters['action_type'] == 'delete_photo') ? 'selected' : ''; ?>>Delete Photo</option>
                        <option value="like_photo" <?php echo (isset($filters['action_type']) && $filters['action_type'] == 'like_photo') ? 'selected' : ''; ?>>Like Photo</option>
                        <option value="comment" <?php echo (isset($filters['action_type']) && $filters['action_type'] == 'comment') ? 'selected' : ''; ?>>Comment</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="status">Status</label>
                    <select name="status" id="status">
                        <option value="">All Statuses</option>
                        <option value="success" <?php echo (isset($filters['status']) && $filters['status'] == 'success') ? 'selected' : ''; ?>>Success</option>
                        <option value="failed" <?php echo (isset($filters['status']) && $filters['status'] == 'failed') ? 'selected' : ''; ?>>Failed</option>
                        <option value="warning" <?php echo (isset($filters['status']) && $filters['status'] == 'warning') ? 'selected' : ''; ?>>Warning</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="user_id">User</label>
                    <select name="user_id" id="user_id">
                        <option value="">All Users</option>
                        <?php foreach ($all_users as $user): ?>
                            <option value="<?php echo $user['id']; ?>" <?php echo (isset($filters['user_id']) && $filters['user_id'] == $user['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['username']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="date_from">From Date</label>
                    <input type="date" name="date_from" id="date_from" value="<?php echo isset($_GET['date_from']) ? $_GET['date_from'] : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="date_to">To Date</label>
                    <input type="date" name="date_to" id="date_to" value="<?php echo isset($_GET['date_to']) ? $_GET['date_to'] : ''; ?>">
                </div>
                
                <div class="filters-actions">
                    <button type="submit" class="btn-filter">
                        <i class="fas fa-search"></i> Filter
                    </button>
                    <a href="admin_logs.php" class="btn-reset" style="text-decoration: none; display: inline-block; line-height: 1.5;">
                        <i class="fas fa-redo"></i> Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Logs Table -->
        <div class="card">
            <h2 class="card-title">
                <i class="fas fa-list"></i> Activity Logs (<?php echo number_format($total_logs); ?> total)
            </h2>
            
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Time</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Description</th>
                            <th>IP Address</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($logs)): ?>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><?php echo $log['id']; ?></td>
                                    <td><?php echo date('M d, Y H:i:s', strtotime($log['created_at'])); ?></td>
                                    <td>
                                        <?php if ($log['user_username']): ?>
                                            <strong><?php echo htmlspecialchars($log['user_username']); ?></strong>
                                        <?php elseif ($log['admin_username']): ?>
                                            <strong><?php echo htmlspecialchars($log['admin_username']); ?></strong> 
                                            <span class="badge badge-admin" style="font-size: 10px;">Admin</span>
                                        <?php else: ?>
                                            <span style="color: #999;">System</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="action-badge">
                                            <?php echo ucwords(str_replace('_', ' ', $log['action_type'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="log-description" title="<?php echo htmlspecialchars($log['action_description']); ?>">
                                            <?php echo htmlspecialchars($log['action_description']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="ip-address"><?php echo htmlspecialchars($log['ip_address']); ?></span>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $log['status']; ?>">
                                            <?php echo $log['status']; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 40px; color: #999;">
                                    No logs found matching your criteria.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo ($page - 1); ?><?php echo http_build_query(array_diff_key($_GET, ['page' => '']), '', '&'); ?>">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php endif; ?>
                    
                    <?php
                    $start = max(1, $page - 2);
                    $end = min($total_pages, $page + 2);
                    
                    for ($i = $start; $i <= $end; $i++):
                    ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?><?php echo http_build_query(array_diff_key($_GET, ['page' => '']), '', '&'); ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo ($page + 1); ?><?php echo http_build_query(array_diff_key($_GET, ['page' => '']), '', '&'); ?>">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>