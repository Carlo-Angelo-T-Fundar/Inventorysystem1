<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Authentication helper functions

function getCurrentUserRole($conn) {
    if (isset($_SESSION['user_id'])) {
        $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        return $user['role'] ?? 'user';
    }
    return 'guest';
}

function requireAuth() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }
}

function requireRole($requiredRoles, $conn) {
    requireAuth();
    $userRole = getCurrentUserRole($conn);
    
    if (is_string($requiredRoles)) {
        $requiredRoles = [$requiredRoles];
    }
    
    if (!in_array($userRole, $requiredRoles)) {
        header('Location: dashboard.php?error=access_denied');
        exit();
    }
}
?>
