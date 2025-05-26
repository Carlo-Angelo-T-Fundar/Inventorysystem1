<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Function to check if user has required role
function checkUserRole($required_roles, $conn) {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return false;
    }
    
    $user = $result->fetch_assoc();
    $user_role = $user['role'];
    
    // If required_roles is a string, convert to array
    if (is_string($required_roles)) {
        $required_roles = [$required_roles];
    }
    
    return in_array($user_role, $required_roles);
}

// Function to get current user role
function getCurrentUserRole($conn) {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return null;
    }
    
    $user = $result->fetch_assoc();
    return $user['role'];
}

// Function to redirect unauthorized users
function requireRole($required_roles, $conn, $redirect_url = "../index.php") {
    if (!checkUserRole($required_roles, $conn)) {
        header("Location: " . $redirect_url);
        exit();
    }
}

// Function to check if current user is an admin
function isAdmin() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        return false;
    }
    
    return $_SESSION['role'] === 'admin';
}
?>
