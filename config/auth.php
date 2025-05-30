<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Update last activity timestamp
$_SESSION['last_activity'] = time();

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

/**
 * Include auto-logout JavaScript for authenticated pages
 */
function includeAutoLogout() {
    $userId = $_SESSION['user_id'] ?? 0;
    echo "
    <!-- Auto-logout system -->
    <script>
        // Set user data for auto-logout
        if (!document.body.classList.contains('logged-in')) {
            document.body.classList.add('logged-in');
        }
        if (!document.body.hasAttribute('data-user-id')) {
            document.body.setAttribute('data-user-id', '{$userId}');
        }
    </script>
    <script src='css/auto-logout.js'></script>
    ";
}
?>
