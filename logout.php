<?php
session_start();
require_once 'config/db.php';
require_once 'config/activity_logger.php';

// Initialize activity logger and log logout before destroying session
$activityLogger = new UserActivityLogger($conn);
$activityLogger->logLogout();

// Clear all session variables
$_SESSION = array();

// If a session cookie is used, delete it
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to login page
header("Location: login.php");
exit();
