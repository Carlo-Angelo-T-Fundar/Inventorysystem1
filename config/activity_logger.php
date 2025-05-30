<?php
/**
 * User Activity Logger
 * Tracks user login/logout activities and session durations
 */

class UserActivityLogger {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
        $this->createTableIfNotExists();
    }
    
    /**
     * Create the user_activity_logs table if it doesn't exist
     */
    private function createTableIfNotExists() {
        $sql = "CREATE TABLE IF NOT EXISTS user_activity_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            username VARCHAR(50) NOT NULL,
            user_role VARCHAR(20) NOT NULL,
            activity_type ENUM('login', 'logout', 'session_timeout', 'browser_close') NOT NULL,
            login_time TIMESTAMP NULL,
            logout_time TIMESTAMP NULL,
            session_duration INT NULL COMMENT 'Duration in seconds',
            ip_address VARCHAR(45) NULL,
            user_agent TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_id (user_id),
            INDEX idx_activity_type (activity_type),
            INDEX idx_login_time (login_time),
            INDEX idx_created_at (created_at)
        )";
        
        $this->conn->query($sql);
    }
    
    /**
     * Log user login activity
     */
    public function logLogin($user_id, $username, $user_role) {
        $ip_address = $this->getClientIP();
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $login_time = date('Y-m-d H:i:s');
        
        $stmt = $this->conn->prepare("
            INSERT INTO user_activity_logs 
            (user_id, username, user_role, activity_type, login_time, ip_address, user_agent) 
            VALUES (?, ?, ?, 'login', ?, ?, ?)
        ");
        
        $stmt->bind_param("isssss", $user_id, $username, $user_role, $login_time, $ip_address, $user_agent);
        $result = $stmt->execute();
        
        if ($result) {
            // Store login log ID in session for later logout tracking
            $_SESSION['login_log_id'] = $this->conn->insert_id;
            $_SESSION['login_time'] = time();
        }
        
        return $result;
    }
      /**
     * Log user logout activity
     */
    public function logLogout($user_id = null, $username = null, $user_role = null, $ip_address = null, $user_agent = null, $reason = 'logout') {
        // Get user info from session if not provided
        if (!$user_id && isset($_SESSION['user_id'])) {
            $user_id = $_SESSION['user_id'];
        }
        if (!$username && isset($_SESSION['username'])) {
            $username = $_SESSION['username'];
        }        if (!$user_role && isset($_SESSION['role'])) {
            $user_role = $_SESSION['role'];
        }
        if (!$ip_address) {
            $ip_address = $this->getClientIP();
        }
        if (!$user_agent) {
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        }
        // Debug logging
        error_log("LOGOUT DEBUG: user_id=$user_id, username=$username, user_role=$user_role, reason=$reason");
        error_log("LOGOUT DEBUG: Session login_log_id=" . ($_SESSION['login_log_id'] ?? 'not set'));
        
        // Map reason to activity type
        $activity_type = 'logout';
        if ($reason === 'session_timeout') {
            $activity_type = 'session_timeout';
        } elseif ($reason === 'browser_close') {
            $activity_type = 'browser_close';
        }
        
        $logout_time = date('Y-m-d H:i:s');
        $session_duration = 0;
        
        // Calculate session duration if login time is available
        if (isset($_SESSION['login_time'])) {
            $session_duration = time() - $_SESSION['login_time'];
        }
          // Try to update the existing login record first
        $updated = false;
        if (isset($_SESSION['login_log_id']) && $_SESSION['login_log_id']) {
            $stmt = $this->conn->prepare("
                UPDATE user_activity_logs 
                SET logout_time = ?, session_duration = ?
                WHERE id = ? AND user_id = ? AND logout_time IS NULL
            ");            $stmt->bind_param("siii", $logout_time, $session_duration, $_SESSION['login_log_id'], $user_id);
            $result = $stmt->execute();
            $updated = ($stmt->affected_rows > 0);
            error_log("LOGOUT DEBUG: Updated existing record (login_log_id=" . $_SESSION['login_log_id'] . "): " . ($updated ? 'SUCCESS' : 'FAILED') . ", affected_rows=" . $stmt->affected_rows);
        }
        
        // If we couldn't update an existing record, try to find the most recent login without logout
        if (!$updated && $user_id) {
            $stmt = $this->conn->prepare("
                UPDATE user_activity_logs 
                SET logout_time = ?, session_duration = ?
                WHERE user_id = ? AND logout_time IS NULL AND activity_type = 'login'
                ORDER BY login_time DESC LIMIT 1
            ");            $stmt->bind_param("sii", $logout_time, $session_duration, $user_id);
            $result = $stmt->execute();
            $updated = ($stmt->affected_rows > 0);
            error_log("LOGOUT DEBUG: Updated most recent login: " . ($updated ? 'SUCCESS' : 'FAILED') . ", affected_rows=" . $stmt->affected_rows);
        }
        
        // If still no update and this is a special logout type, create a new record
        if (!$updated && in_array($activity_type, ['session_timeout', 'browser_close'])) {
            $stmt = $this->conn->prepare("
                INSERT INTO user_activity_logs 
                (user_id, username, user_role, activity_type, logout_time, session_duration, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");            $stmt->bind_param("isssisss", $user_id, $username, $user_role, $activity_type, $logout_time, $session_duration, $ip_address, $user_agent);
            $result = $stmt->execute();
            $updated = $result;
            error_log("LOGOUT DEBUG: Created new logout record: " . ($updated ? 'SUCCESS' : 'FAILED'));
        }
        
        error_log("LOGOUT DEBUG: Final result: " . ($updated ? 'SUCCESS' : 'FAILED'));
        return $updated;
    }
    
    /**
     * Get all activity logs with optional filtering
     */
    public function getActivityLogs($limit = 100, $offset = 0, $user_id = null, $activity_type = null, $date_from = null, $date_to = null) {
        $sql = "SELECT * FROM user_activity_logs WHERE 1=1";
        $params = [];
        $types = "";
        
        if ($user_id) {
            $sql .= " AND user_id = ?";
            $params[] = $user_id;
            $types .= "i";
        }
        
        if ($activity_type) {
            $sql .= " AND activity_type = ?";
            $params[] = $activity_type;
            $types .= "s";
        }
        
        if ($date_from) {
            $sql .= " AND DATE(created_at) >= ?";
            $params[] = $date_from;
            $types .= "s";
        }
        
        if ($date_to) {
            $sql .= " AND DATE(created_at) <= ?";
            $params[] = $date_to;
            $types .= "s";
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";
        
        $stmt = $this->conn->prepare($sql);
        if ($types) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Get activity statistics
     */
    public function getActivityStats($date_from = null, $date_to = null) {
        $where_clause = "";
        $params = [];
        $types = "";
        
        if ($date_from && $date_to) {
            $where_clause = "WHERE DATE(created_at) BETWEEN ? AND ?";
            $params = [$date_from, $date_to];
            $types = "ss";
        }
        
        $sql = "SELECT 
            COUNT(*) as total_activities,
            COUNT(CASE WHEN activity_type = 'login' THEN 1 END) as total_logins,
            COUNT(CASE WHEN activity_type = 'logout' THEN 1 END) as total_logouts,
            COUNT(CASE WHEN activity_type = 'session_timeout' THEN 1 END) as total_timeouts,
            COUNT(DISTINCT user_id) as unique_users,
            AVG(session_duration) as avg_session_duration,
            MAX(session_duration) as max_session_duration,
            MIN(session_duration) as min_session_duration
            FROM user_activity_logs 
            $where_clause";
        
        $stmt = $this->conn->prepare($sql);
        if ($types) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        
        return $stmt->get_result()->fetch_assoc();
    }
    
    /**
     * Get currently active users (logged in but not logged out)
     */
    public function getActiveUsers() {
        $sql = "SELECT DISTINCT u.username, u.role, ual.login_time, ual.ip_address
                FROM user_activity_logs ual
                JOIN users u ON ual.user_id = u.id
                WHERE ual.activity_type = 'login' 
                AND ual.logout_time IS NULL 
                AND ual.login_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                ORDER BY ual.login_time DESC";
        
        $result = $this->conn->query($sql);
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }
    
    /**
     * Get client IP address
     */
    private function getClientIP() {
        $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
      /**
     * Format session duration for display
     */
    public static function formatDuration($seconds) {
        if (!$seconds) return 'N/A';
        
        // Convert to numeric and then to integer to avoid float-to-int conversion warnings
        $seconds = (int) round((float) $seconds);
        
        $hours = intval($seconds / 3600);
        $minutes = intval(($seconds % 3600) / 60);
        $secs = intval($seconds % 60);
        
        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
        } else {
            return sprintf('%02d:%02d', $minutes, $secs);
        }
    }
    
    /**
     * Clean old logs (older than specified days)
     */
    public function cleanOldLogs($days = 90) {
        $sql = "DELETE FROM user_activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $days);
        
        return $stmt->execute();
    }
}
?>
