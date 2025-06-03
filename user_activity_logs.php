<?php
// user activity logs page - shows what users are doing in the system
// learned about activity tracking in my security class - pretty important for monitoring
require_once 'config/db.php';
require_once 'config/auth.php';
require_once 'config/activity_logger.php';

// Check if user is logged in and has admin access
// only admins should see this stuff - that would be bad if everyone could!
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Only admins can view activity logs
requireRole(['admin'], $conn);

// Initialize activity logger - this handles all the logging stuff
$activityLogger = new UserActivityLogger($conn);

// Handle pagination - learned about this for big data sets
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50; // show 50 logs per page
$offset = ($page - 1) * $limit;

// Handle filters - users can filter by different criteria
$user_filter = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$activity_filter = isset($_GET['activity_type']) ? $_GET['activity_type'] : null;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : null;
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : null;

// Get activity logs with filters applied
$logs = $activityLogger->getActivityLogs($limit, $offset, $user_filter, $activity_filter, $date_from, $date_to);

// Get statistics for the dashboard cards
$stats = $activityLogger->getActivityStats($date_from, $date_to);

// Get currently active users - shows who's online right now
$active_users = $activityLogger->getActiveUsers();

// Get all users for the filter dropdown menu
$users_result = $conn->query("SELECT id, username FROM users ORDER BY username");
$all_users = $users_result ? $users_result->fetch_all(MYSQLI_ASSOC) : [];

// Handle log cleanup - admins can delete old logs to save space
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'cleanup_logs') {
        $days = isset($_POST['cleanup_days']) ? (int)$_POST['cleanup_days'] : 90;
        if ($activityLogger->cleanOldLogs($days)) {
            $success = "Logs older than $days days have been cleaned up successfully.";
        } else {
            $error = "Failed to clean up old logs.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Activity Logs - Inventory System</title>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap">
    <!-- our css files -->
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/sidebar.css">
</head>
<body>    <div class="dashboard-container">
        <!-- Include Sidebar -->
        <?php 
        $current_page = 'user_activity_logs';
        require_once 'templates/sidebar.php'; 
        ?>

        <!-- Main Content -->
        <main class="main-content">
            <header class="dashboard-header">
                <h1>📝 User Activity Logs</h1>
                <div class="header-actions">
                    <button class="btn btn-secondary" onclick="openCleanupModal()">
                        🧹 Cleanup Old Logs
                    </button>
                    <button class="btn btn-primary" onclick="exportLogs()">
                        📥 Export Logs
                    </button>
                </div>
            </header>

            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?><!-- Statistics Cards - shows basic stats about user activity -->
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-icon activity">
                        📊
                    </div>
                    <div class="stat-details">
                        <span class="stat-title">Total Activities</span>
                        <span class="stat-value"><?php echo number_format($stats['total_activities']); ?></span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon login">
                        🔐
                    </div>
                    <div class="stat-details">
                        <span class="stat-title">Total Logins</span>
                        <span class="stat-value"><?php echo number_format($stats['total_logins']); ?></span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon users">
                        👥
                    </div>
                    <div class="stat-details">
                        <span class="stat-title">Unique Users</span>
                        <span class="stat-value"><?php echo number_format($stats['unique_users']); ?></span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon duration">
                        ⏰
                    </div>
                    <div class="stat-details">
                        <span class="stat-title">Avg Session</span>
                        <span class="stat-value"><?php echo UserActivityLogger::formatDuration($stats['avg_session_duration']); ?></span>
                    </div>
                </div>
            </div>

            <!-- Active Users Section - shows who's currently online -->
            <?php if (!empty($active_users)): ?>
            <div class="card">
                <div class="card-header">
                    <h3>✅ Currently Active Users</h3>
                </div>
                <div class="card-body">
                    <div class="active-users-grid">
                        <?php foreach ($active_users as $active_user): ?>
                        <div class="active-user-card">
                            <div class="user-info">
                                <div class="user-name"><?php echo htmlspecialchars($active_user['username']); ?></div>
                                <div class="user-role"><?php echo htmlspecialchars(ucfirst($active_user['role'])); ?></div>
                            </div>
                            <div class="session-info">                                <div class="login-time">
                                    🕐
                                    <?php echo date('M j, Y H:i', strtotime($active_user['login_time'])); ?>
                                </div>
                                <div class="ip-address">
                                    🌐
                                    <?php echo htmlspecialchars($active_user['ip_address']); ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="filters-section">
                <form method="GET" class="filters-form">
                    <div class="filter-group">
                        <label for="user_id">User:</label>
                        <select id="user_id" name="user_id">
                            <option value="">All Users</option>
                            <?php foreach ($all_users as $user): ?>
                            <option value="<?php echo $user['id']; ?>" 
                                    <?php echo ($user_filter == $user['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['username']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="activity_type">Activity:</label>
                        <select id="activity_type" name="activity_type">
                            <option value="">All Activities</option>
                            <option value="login" <?php echo ($activity_filter === 'login') ? 'selected' : ''; ?>>Login</option>
                            <option value="logout" <?php echo ($activity_filter === 'logout') ? 'selected' : ''; ?>>Logout</option>
                            <option value="session_timeout" <?php echo ($activity_filter === 'session_timeout') ? 'selected' : ''; ?>>Session Timeout</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="date_from">From:</label>
                        <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from ?? ''); ?>">
                    </div>

                    <div class="filter-group">
                        <label for="date_to">To:</label>
                        <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to ?? ''); ?>">
                    </div>

                    <div class="filter-group">
                        <label for="limit">Per Page:</label>
                        <select id="limit" name="limit">
                            <option value="25" <?php echo ($limit === 25) ? 'selected' : ''; ?>>25</option>
                            <option value="50" <?php echo ($limit === 50) ? 'selected' : ''; ?>>50</option>
                            <option value="100" <?php echo ($limit === 100) ? 'selected' : ''; ?>>100</option>
                        </select>
                    </div>                    <button type="submit" class="btn btn-primary">
                        🔍 Apply Filters
                    </button>
                    <a href="user_activity_logs.php" class="btn btn-secondary">
                        ❌ Clear
                    </a>
                </form>
            </div>

            <!-- Activity Logs Table - the main part showing all the user activities -->
            <div class="card">
                <div class="card-header">
                    <h3>📋 Activity Logs</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Role</th>
                                    <th>Activity</th>
                                    <th>Login Time</th>
                                    <th>Logout Time</th>
                                    <th>Duration</th>
                                    <th>IP Address</th>
                                    <th>User Agent</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td>#<?php echo $log['id']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($log['username']); ?></strong>
                                    </td>
                                    <td>
                                        <span class="role-badge <?php echo strtolower($log['user_role']); ?>">
                                            <?php echo htmlspecialchars(ucfirst($log['user_role'])); ?>
                                        </span>
                                    </td>
                                    <td>                                        <span class="activity-badge <?php echo $log['activity_type']; ?>">
                                            <?php echo ($log['activity_type'] === 'login') ? '🔐' : '🚪'; ?>
                                            <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $log['activity_type']))); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo $log['login_time'] ? date('M j, Y H:i:s', strtotime($log['login_time'])) : 'N/A'; ?>
                                    </td>
                                    <td>
                                        <?php echo $log['logout_time'] ? date('M j, Y H:i:s', strtotime($log['logout_time'])) : 'N/A'; ?>
                                    </td>
                                    <td>
                                        <?php echo UserActivityLogger::formatDuration($log['session_duration']); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                                    <td>
                                        <span class="user-agent" title="<?php echo htmlspecialchars($log['user_agent']); ?>">
                                            <?php echo htmlspecialchars(substr($log['user_agent'], 0, 50) . (strlen($log['user_agent']) > 50 ? '...' : '')); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                
                                <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="9" class="text-center">No activity logs found</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="pagination-wrapper">
                        <div class="pagination-info">
                            Showing page <?php echo $page; ?> of logs
                        </div>
                        <div class="pagination-links">
                            <?php if ($page > 1): ?>                            <a href="?page=<?php echo ($page-1); ?>&<?php echo http_build_query(array_filter($_GET, function($key) { return $key !== 'page'; }, ARRAY_FILTER_USE_KEY)); ?>" 
                               class="btn btn-sm btn-secondary">
                                ⬅️ Previous
                            </a>
                            <?php endif; ?>
                            
                            <?php if (count($logs) === $limit): ?>
                            <a href="?page=<?php echo ($page+1); ?>&<?php echo http_build_query(array_filter($_GET, function($key) { return $key !== 'page'; }, ARRAY_FILTER_USE_KEY)); ?>" 
                               class="btn btn-sm btn-secondary">
                                Next ➡️
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Cleanup Modal -->
    <div id="cleanupModal" class="modal">
        <div class="modal-content">
            <h2>Cleanup Old Activity Logs</h2>
            <form method="POST">
                <input type="hidden" name="action" value="cleanup_logs">
                
                <div class="form-group">
                    <label for="cleanup_days">Delete logs older than (days):</label>
                    <input type="number" id="cleanup_days" name="cleanup_days" value="90" min="1" max="365" required class="form-control">
                    <small class="form-text">Logs older than the specified number of days will be permanently deleted.</small>
                </div>

                <div class="form-actions">                    <button type="submit" class="btn btn-danger">
                        🗑️ Delete Old Logs
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeCleanupModal()">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal functions
        function openCleanupModal() {
            document.getElementById('cleanupModal').style.display = 'block';
        }

        function closeCleanupModal() {
            document.getElementById('cleanupModal').style.display = 'none';
        }        // Export logs function
        function exportLogs() {
            const format = prompt('Choose export format:\n\nEnter "csv" for CSV format\nEnter "json" for JSON format', 'csv');
            
            if (format && (format.toLowerCase() === 'csv' || format.toLowerCase() === 'json')) {
                const params = new URLSearchParams(window.location.search);
                params.set('export', format.toLowerCase());
                window.location.href = 'export_activity_logs.php?' + params.toString();
            } else if (format !== null) {
                alert('Invalid format. Please choose "csv" or "json".');
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target == document.getElementById('cleanupModal')) {
                closeCleanupModal();
            }
        }

        // Auto-refresh active users every 30 seconds
        setInterval(function() {
            // Only refresh if no filters are applied to avoid disrupting user's work
            if (window.location.search === '' || window.location.search === '?') {
                location.reload();
            }
        }, 30000);
    </script>

    <style>
        /* Ensure sidebar styles take priority */
        .sidebar {
            width: 250px !important;
            min-height: 100vh !important;
            background: #f8f9fa !important;
            color: #333 !important;
            padding: 15px !important;
            display: flex !important;
            flex-direction: column !important;
            position: fixed !important;
            left: 0 !important;
            top: 0 !important;
            border-right: 1px solid #ddd !important;
        }

        .sidebar .admin-header {
            margin-bottom: 20px !important;
            padding: 15px !important;
            border-bottom: 1px solid #ddd !important;
        }

        .sidebar .profile-section {
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
        }

        .sidebar .admin-avatar {
            width: 40px !important;
            height: 40px !important;
            background-color: #e9ecef !important;
            border-radius: 50% !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            border: 1px solid #ccc !important;
            font-size: 20px !important;
        }

        .sidebar .profile-info {
            flex: 1 !important;
            display: flex !important;
            flex-direction: column !important;
            gap: 5px !important;
        }

        .sidebar .username {
            font-size: 16px !important;
            font-weight: bold !important;
            color: #333 !important;
            margin: 0 !important;
        }

        .sidebar .user-role {
            display: flex !important;
            align-items: center !important;
        }

        .sidebar .role-badge {
            font-size: 12px !important;
            font-weight: bold !important;
            padding: 3px 8px !important;
            border-radius: 3px !important;
            text-transform: uppercase !important;
        }

        .sidebar .role-admin {
            background-color: #ffcccc !important;
            color: #990000 !important;
        }

        .sidebar .role-store_clerk {
            background-color: #ccffcc !important;
            color: #006600 !important;
        }

        .sidebar .role-cashier {
            background-color: #ffffcc !important;
            color: #996600 !important;
        }

        .sidebar .online-status {
            display: flex !important;
            align-items: center !important;
            gap: 5px !important;
            font-size: 14px !important;
            color: #009900 !important;
            font-weight: 500 !important;
        }

        /* Navigation styles */
        .sidebar .sidebar-nav {
            flex: 1 !important;
            display: flex !important;
            flex-direction: column !important;
            gap: 8px !important;
            margin-bottom: 20px !important;
        }

        .sidebar .nav-link {
            color: #333 !important;
            text-decoration: none !important;
            padding: 12px 15px !important;
            border-radius: 3px !important;
            background-color: #fff !important;
            border: 1px solid #ddd !important;
            font-weight: normal !important;
        }

        .sidebar .nav-link:hover {
            background-color: #e9ecef !important;
            color: #000 !important;
        }

        .sidebar .nav-link.active {
            background-color: #0066cc !important;
            color: white !important;
            font-weight: bold !important;
        }

        /* Sidebar footer styles */
        .sidebar .sidebar-footer {
            margin-top: auto !important;
            display: flex !important;
            flex-direction: column !important;
            gap: 8px !important;
        }

        .sidebar .sidebar-footer a {
            text-decoration: none !important;
            display: flex !important;
            align-items: center !important;
            gap: 8px !important;
            padding: 10px !important;
            border-radius: 3px !important;
            font-weight: normal !important;
            color: #333 !important;
        }

        .sidebar .sidebar-footer a:hover {
            background-color: #f8f9fa !important;
        }

        .sidebar .sidebar-footer a:first-child {
            color: #0066cc !important;
        }

        .sidebar .sidebar-footer a:first-child:hover {
            background-color: #e6f3ff !important;
        }

        .sidebar .sidebar-footer a.logout {
            color: #cc0000 !important;
        }

        .sidebar .sidebar-footer a.logout:hover {
            background-color: #ffe6e6 !important;
        }

        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 0.5rem;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .stat-icon.activity { background: #8b5cf6; }
        .stat-icon.login { background: #10b981; }
        .stat-icon.users { background: #3b82f6; }
        .stat-icon.duration { background: #f59e0b; }

        .stat-details {
            flex: 1;
        }

        .stat-title {
            display: block;
            font-size: 0.875rem;
            color: #6b7280;
            margin-bottom: 0.25rem;
        }

        .stat-value {
            display: block;
            font-size: 1.5rem;
            font-weight: 600;
            color: #111827;
        }

        .active-users-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1rem;
        }

        .active-user-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            padding: 1rem;
        }

        .user-info .user-name {
            font-weight: 600;
            color: #1a202c;
        }

        .user-info .user-role {
            font-size: 0.875rem;
            color: #718096;
        }

        .session-info {
            margin-top: 0.5rem;
            font-size: 0.875rem;
            color: #4a5568;
        }

        .session-info div {
            margin-bottom: 0.25rem;
        }

        .session-info i {
            width: 16px;
            margin-right: 0.5rem;
        }

        .filters-section {
            background: white;
            border-radius: 0.5rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .filters-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: #374151;
        }

        .filter-group input,
        .filter-group select {
            padding: 0.5rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            font-size: 0.875rem;
        }

        .activity-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .activity-badge.login {
            background: #d1fae5;
            color: #065f46;
        }

        .activity-badge.logout {
            background: #fee2e2;
            color: #991b1b;
        }

        .activity-badge.session_timeout {
            background: #fef3c7;
            color: #92400e;
        }

        .role-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
        }

        .role-badge.admin {
            background: #dbeafe;
            color: #1e40af;
        }

        .role-badge.store_clerk {
            background: #d1fae5;
            color: #065f46;
        }

        .role-badge.cashier {
            background: #fef3c7;
            color: #92400e;
        }

        .role-badge.supplier {
            background: #e0e7ff;
            color: #3730a3;
        }

        .table-responsive {
            overflow-x: auto;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }

        .table th,
        .table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
            white-space: nowrap;
        }

        .table th {
            background: #f9fafb;
            font-weight: 600;
            color: #374151;
        }

        .table tr:hover {
            background: #f9fafb;
        }

        .user-agent {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            cursor: help;
        }

        .pagination-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e5e7eb;
        }

        .pagination-links {
            display: flex;
            gap: 0.5rem;
        }

        .card {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
        }

        .card-header {
            padding: 1.5rem 1.5rem 0;
        }

        .card-header h3 {
            margin: 0;
            color: #1f2937;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-body {
            padding: 1.5rem;
        }

        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .content-header h1 {
            margin: 0;
            color: #1f2937;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .header-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            background: white;
            color: #374151;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn:hover {
            background: #f9fafb;
        }

        .btn-primary {
            background: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }

        .btn-primary:hover {
            background: #2563eb;
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
            border-color: #6b7280;
        }

        .btn-secondary:hover {
            background: #4b5563;
        }

        .btn-danger {
            background: #dc2626;
            color: white;
            border-color: #dc2626;
        }

        .btn-danger:hover {
            background: #b91c1c;
        }

        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.8125rem;
        }

        .alert {
            padding: 1rem;
            margin: 1rem 0;
            border-radius: 0.375rem;
            font-weight: 500;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #6ee7b7;
        }

        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 2rem;
            border-radius: 0.5rem;
            width: 90%;
            max-width: 500px;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            font-size: 0.875rem;
        }

        .form-text {
            font-size: 0.75rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }

        .form-actions {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-end;
            margin-top: 1.5rem;
        }

        .text-center {
            text-align: center;
        }
    </style>

    <!-- Auto-logout system -->
    <script src="css/auto-logout.js"></script>
    <script>
        // Mark body as logged in for auto-logout detection
        document.body.classList.add('logged-in');
        document.body.setAttribute('data-user-id', '<?php echo $_SESSION['user_id']; ?>');
    </script>
</body>
</html>
