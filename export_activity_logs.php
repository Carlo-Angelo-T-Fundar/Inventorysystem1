<?php
require_once 'config/db.php';
require_once 'config/auth.php';
require_once 'config/activity_logger.php';

// Check if user is logged in and has admin access
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Only admins can export activity logs
requireRole(['admin'], $conn);

// Initialize activity logger
$activityLogger = new UserActivityLogger($conn);

// Handle filters
$user_filter = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$activity_filter = isset($_GET['activity_type']) ? $_GET['activity_type'] : null;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : null;
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : null;

// Get all matching logs for export (no pagination)
$logs = $activityLogger->getActivityLogs(null, null, $user_filter, $activity_filter, $date_from, $date_to);

// Determine export format
$export_format = isset($_GET['export']) ? $_GET['export'] : 'csv';

if ($export_format === 'csv') {
    // Generate CSV export
    $filename = 'user_activity_logs_' . date('Y-m-d_H-i-s') . '.csv';
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Content-Description: File Transfer');
    
    $output = fopen('php://output', 'w');
    
    // CSV headers
    fputcsv($output, [
        'ID',
        'Username', 
        'User Role',
        'Activity Type',
        'Login Time',
        'Logout Time',
        'Session Duration',
        'Session Duration (Formatted)',
        'IP Address',
        'User Agent',
        'Created At'
    ]);
    
    // CSV data
    foreach ($logs as $log) {
        fputcsv($output, [
            $log['id'],
            $log['username'],
            $log['user_role'],
            $log['activity_type'],
            $log['login_time'] ? date('Y-m-d H:i:s', strtotime($log['login_time'])) : '',
            $log['logout_time'] ? date('Y-m-d H:i:s', strtotime($log['logout_time'])) : '',
            $log['session_duration'] ?? '',
            $log['session_duration'] ? UserActivityLogger::formatDuration($log['session_duration']) : '',
            $log['ip_address'] ?? '',
            $log['user_agent'] ?? '',
            $log['created_at'] ? date('Y-m-d H:i:s', strtotime($log['created_at'])) : ''
        ]);
    }
    
    fclose($output);
    exit();
    
} elseif ($export_format === 'json') {
    // Generate JSON export
    $filename = 'user_activity_logs_' . date('Y-m-d_H-i-s') . '.json';
    
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Content-Description: File Transfer');
    
    // Format data for JSON export
    $export_data = [
        'export_info' => [
            'generated_at' => date('Y-m-d H:i:s'),
            'exported_by' => $_SESSION['username'],
            'total_records' => count($logs),
            'filters_applied' => [
                'user_id' => $user_filter,
                'activity_type' => $activity_filter,
                'date_from' => $date_from,
                'date_to' => $date_to
            ]
        ],
        'activity_logs' => []
    ];
    
    foreach ($logs as $log) {
        $export_data['activity_logs'][] = [
            'id' => (int)$log['id'],
            'username' => $log['username'],
            'user_role' => $log['user_role'],
            'activity_type' => $log['activity_type'],
            'login_time' => $log['login_time'],
            'logout_time' => $log['logout_time'],
            'session_duration_seconds' => $log['session_duration'] ? (int)$log['session_duration'] : null,
            'session_duration_formatted' => $log['session_duration'] ? UserActivityLogger::formatDuration($log['session_duration']) : null,
            'ip_address' => $log['ip_address'],
            'user_agent' => $log['user_agent'],
            'created_at' => $log['created_at']
        ];
    }
    
    echo json_encode($export_data, JSON_PRETTY_PRINT);
    exit();
    
} else {
    // Invalid export format
    header("Location: user_activity_logs.php?error=invalid_export_format");
    exit();
}
?>
