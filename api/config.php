<?php
// API Configuration file
header('Content-Type: application/json');

// Allow CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database connection
require_once '../config/db.php';

// API helper functions
function send_response($data, $status_code = 200) {
    http_response_code($status_code);
    echo json_encode($data);
    exit();
}

function send_error($message, $status_code = 400, $error_code = null) {
    $response = [
        'status' => 'error',
        'message' => $message
    ];
    
    if ($error_code !== null) {
        $response['error_code'] = $error_code;
    }
    
    http_response_code($status_code);
    echo json_encode($response);
    exit();
}

// Get API token from Authorization header
function get_api_token() {
    $headers = getallheaders();
    $auth_header = isset($headers['Authorization']) ? $headers['Authorization'] : '';
    
    if (empty($auth_header)) {
        return null;
    }
    
    // Check if it's a Bearer token
    if (preg_match('/Bearer\s(\S+)/', $auth_header, $matches)) {
        return $matches[1];
    }
    
    return null;
}

// Verify API token (validates session token)
function verify_api_token($token, $conn) {
    if (empty($token)) {
        return false;
    }
    
    // Check if this is a valid session token in the database
    $stmt = $conn->prepare("SELECT id, user_id, expiry FROM api_tokens WHERE token = ? AND expiry > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return false;
    }
    
    $token_data = $result->fetch_assoc();
    return $token_data['user_id'];
}

// Ensure we have the api_tokens table
$create_tokens_table = "CREATE TABLE IF NOT EXISTS api_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL,
    expiry DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (token),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";

$conn->query($create_tokens_table);

// Get JSON data from request body
function get_json_data() {
    $json = file_get_contents('php://input');
    if (empty($json)) {
        return [];
    }
    
    $data = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        send_error('Invalid JSON data', 400, 'INVALID_JSON');
    }
    
    return $data;
}

// Require authentication for protected endpoints
function require_auth($conn) {
    $token = get_api_token();
    $user_id = verify_api_token($token, $conn);
    
    if (!$user_id) {
        send_error('Unauthorized access', 401, 'AUTH_REQUIRED');
    }
    
    return $user_id;
}

// Check user role
function check_user_role($user_id, $required_roles, $conn) {
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

// Require specific role for endpoints
function require_role($user_id, $required_roles, $conn) {
    if (!check_user_role($user_id, $required_roles, $conn)) {
        send_error('You do not have permission to access this resource', 403, 'ACCESS_DENIED');
    }
}

// Generate API token
function generate_api_token($user_id, $conn) {
    // Generate a random token
    $token = bin2hex(random_bytes(32));
    $expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
    
    // Delete any existing tokens for this user
    $delete_stmt = $conn->prepare("DELETE FROM api_tokens WHERE user_id = ?");
    $delete_stmt->bind_param("i", $user_id);
    $delete_stmt->execute();
    
    // Store the new token
    $stmt = $conn->prepare("INSERT INTO api_tokens (user_id, token, expiry) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $user_id, $token, $expiry);
    $stmt->execute();
    
    return $token;
}
