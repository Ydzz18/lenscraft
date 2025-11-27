<?php
/**
 * User Activity Logger Class
 * Tracks all user and admin activities in the system
 */

class UserLogger {
    private $pdo;
    private $enabled = true;
    
    // Action types constants
    const ACTION_LOGIN = 'login';
    const ACTION_LOGOUT = 'logout';
    const ACTION_REGISTER = 'register';
    const ACTION_UPLOAD_PHOTO = 'upload_photo';
    const ACTION_DELETE_PHOTO = 'delete_photo';
    const ACTION_LIKE_PHOTO = 'like_photo';
    const ACTION_UNLIKE_PHOTO = 'unlike_photo';
    const ACTION_COMMENT = 'comment';
    const ACTION_DELETE_COMMENT = 'delete_comment';
    const ACTION_UPDATE_PROFILE = 'update_profile';
    const ACTION_PASSWORD_CHANGE = 'password_change';
    const ACTION_FAILED_LOGIN = 'failed_login';
    
    // Admin action types
    const ACTION_ADMIN_LOGIN = 'admin_login';
    const ACTION_ADMIN_DELETE_USER = 'admin_delete_user';
    const ACTION_ADMIN_DELETE_PHOTO = 'admin_delete_photo';
    const ACTION_ADMIN_DELETE_COMMENT = 'admin_delete_comment';
    const ACTION_ADMIN_TOGGLE_STATUS = 'admin_toggle_status';
    const ACTION_ADMIN_ADD_ADMIN = 'admin_add_admin';
    const ACTION_ADMIN_DELETE_ADMIN = 'admin_delete_admin';
    
    // Status types
    const STATUS_SUCCESS = 'success';
    const STATUS_FAILED = 'failed';
    const STATUS_WARNING = 'warning';
    
    public function __construct($pdo = null) {
        try {
            if ($pdo === null) {
                require_once __DIR__ . '/db_connect.php';
                $this->pdo = getDBConnection();
            } else {
                $this->pdo = $pdo;
            }
            
            // Check if user_logs table exists
            $this->checkTableExists();
        } catch (Exception $e) {
            error_log("Logger initialization error: " . $e->getMessage());
            $this->enabled = false;
        }
    }
    
    /**
     * Check if user_logs table exists
     */
    private function checkTableExists() {
        try {
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'user_logs'");
            if ($stmt->rowCount() === 0) {
                // Table doesn't exist, try to create it
                $this->createLogTable();
            }
        } catch (PDOException $e) {
            error_log("Error checking user_logs table: " . $e->getMessage());
            $this->enabled = false;
        }
    }
    
    /**
     * Create user_logs table if it doesn't exist
     */
    private function createLogTable() {
        try {
            $sql = "CREATE TABLE IF NOT EXISTS user_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                admin_id INT NULL,
                action_type VARCHAR(50) NOT NULL,
                action_description TEXT NOT NULL,
                ip_address VARCHAR(45) NULL,
                user_agent VARCHAR(500) NULL,
                affected_table VARCHAR(50) NULL,
                affected_id INT NULL,
                status VARCHAR(20) DEFAULT 'success',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_id (user_id),
                INDEX idx_action_type (action_type),
                INDEX idx_created_at (created_at),
                INDEX idx_status (status)
            )";
            
            $this->pdo->exec($sql);
            $this->enabled = true;
        } catch (PDOException $e) {
            error_log("Error creating user_logs table: " . $e->getMessage());
            $this->enabled = false;
        }
    }
    
    /**
     * Log a user action
     * 
     * @param string $action_type Type of action performed
     * @param string $description Human-readable description
     * @param int|null $user_id User ID (null for public actions)
     * @param int|null $admin_id Admin ID (for admin actions)
     * @param string|null $affected_table Table affected by action
     * @param int|null $affected_id Record ID affected
     * @param string $status Status of the action (success/failed/warning)
     * @return bool Success status
     */
    public function log(
        $action_type, 
        $description, 
        $user_id = null, 
        $admin_id = null,
        $affected_table = null,
        $affected_id = null,
        $status = self::STATUS_SUCCESS
    ) {
        // If logger is disabled, silently return
        if (!$this->enabled) {
            return false;
        }
        
        try {
            $ip_address = $this->getIpAddress();
            $user_agent = $this->getUserAgent();
            
            $stmt = $this->pdo->prepare("
                INSERT INTO user_logs 
                (user_id, admin_id, action_type, action_description, ip_address, user_agent, affected_table, affected_id, status)
                VALUES 
                (:user_id, :admin_id, :action_type, :description, :ip_address, :user_agent, :affected_table, :affected_id, :status)
            ");
            
            $stmt->execute([
                ':user_id' => $user_id,
                ':admin_id' => $admin_id,
                ':action_type' => $action_type,
                ':description' => $description,
                ':ip_address' => $ip_address,
                ':user_agent' => $user_agent,
                ':affected_table' => $affected_table,
                ':affected_id' => $affected_id,
                ':status' => $status
            ]);
            
            return true;
            
        } catch (PDOException $e) {
            error_log("Logger error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get user's logs
     * 
     * @param int $user_id User ID
     * @param int $limit Number of records to fetch
     * @param int $offset Offset for pagination
     * @return array Array of log records
     */
    public function getUserLogs($user_id, $limit = 50, $offset = 0) {
        if (!$this->enabled) {
            return [];
        }
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM user_logs 
                WHERE user_id = :user_id 
                ORDER BY created_at DESC 
                LIMIT :limit OFFSET :offset
            ");
            
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Get user logs error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get all logs with filters
     * 
     * @param array $filters Filter parameters (action_type, status, date_from, date_to)
     * @param int $limit Number of records
     * @param int $offset Offset for pagination
     * @return array Array of log records
     */
    public function getAllLogs($filters = [], $limit = 100, $offset = 0) {
        if (!$this->enabled) {
            return [];
        }
        
        try {
            $where = [];
            $params = [];
            
            if (!empty($filters['action_type'])) {
                $where[] = "action_type = :action_type";
                $params[':action_type'] = $filters['action_type'];
            }
            
            if (!empty($filters['status'])) {
                $where[] = "status = :status";
                $params[':status'] = $filters['status'];
            }
            
            if (!empty($filters['user_id'])) {
                $where[] = "user_id = :user_id";
                $params[':user_id'] = $filters['user_id'];
            }
            
            if (!empty($filters['date_from'])) {
                $where[] = "created_at >= :date_from";
                $params[':date_from'] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $where[] = "created_at <= :date_to";
                $params[':date_to'] = $filters['date_to'];
            }
            
            $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
            
            $stmt = $this->pdo->prepare("
                SELECT 
                    l.*,
                    u.username as user_username,
                    a.username as admin_username
                FROM user_logs l
                LEFT JOIN users u ON l.user_id = u.id
                LEFT JOIN admins a ON l.admin_id = a.id
                $whereClause
                ORDER BY l.created_at DESC
                LIMIT :limit OFFSET :offset
            ");
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Get all logs error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get total count of logs
     * 
     * @param array $filters Filter parameters
     * @return int Total count
     */
    public function getLogsCount($filters = []) {
        if (!$this->enabled) {
            return 0;
        }
        
        try {
            $where = [];
            $params = [];
            
            if (!empty($filters['action_type'])) {
                $where[] = "action_type = :action_type";
                $params[':action_type'] = $filters['action_type'];
            }
            
            if (!empty($filters['status'])) {
                $where[] = "status = :status";
                $params[':status'] = $filters['status'];
            }
            
            if (!empty($filters['user_id'])) {
                $where[] = "user_id = :user_id";
                $params[':user_id'] = $filters['user_id'];
            }
            
            $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
            
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as total FROM user_logs $whereClause");
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return (int) $result['total'];
            
        } catch (PDOException $e) {
            error_log("Get logs count error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get activity statistics
     * 
     * @param int|null $user_id User ID (null for all users)
     * @param int $days Number of days to look back
     * @return array Statistics array
     */
    public function getActivityStats($user_id = null, $days = 30) {
        if (!$this->enabled) {
            return [];
        }
        
        try {
            $where = $user_id ? "WHERE user_id = :user_id AND" : "WHERE";
            
            $stmt = $this->pdo->prepare("
                SELECT 
                    action_type,
                    COUNT(*) as count,
                    status
                FROM user_logs
                $where created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                GROUP BY action_type, status
                ORDER BY count DESC
            ");
            
            if ($user_id) {
                $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            }
            $stmt->bindParam(':days', $days, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Get activity stats error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Delete old logs
     * 
     * @param int $days Logs older than this will be deleted
     * @return bool Success status
     */
    public function cleanOldLogs($days = 90) {
        if (!$this->enabled) {
            return false;
        }
        
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM user_logs 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)
            ");
            
            $stmt->bindParam(':days', $days, PDO::PARAM_INT);
            return $stmt->execute();
            
        } catch (PDOException $e) {
            error_log("Clean old logs error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get client IP address
     * 
     * @return string IP address
     */
    private function getIpAddress() {
        $ip = '';
        
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        return $ip;
    }
    
    /**
     * Get user agent
     * 
     * @return string User agent string
     */
    private function getUserAgent() {
        return isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 500) : '';
    }
    
    /**
     * Check if logger is enabled
     * 
     * @return bool
     */
    public function isEnabled() {
        return $this->enabled;
    }
}
?>