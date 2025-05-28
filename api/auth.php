<?php
// Authentication API Endpoints
require_once 'config.php';

// Route handling
$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'login':
        handle_login($conn);
        break;
    case 'logout':
        handle_logout($conn);
        break;
    default:
        send_error('Invalid authentication action', 404, 'INVALID_ENDPOINT');
}

// Login handler
function handle_login($conn) {
    // Only allow POST for login
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        send_error('Method not allowed', 405, 'METHOD_NOT_ALLOWED');
    }
    
    // Get JSON data from request body
    $data = get_json_data();
    
    // Check required fields
    if (empty($data['username']) || empty($data['password'])) {
        send_error('Username and password are required', 400, 'MISSING_CREDENTIALS');
    }
    
    $username = $data['username'];
    $password = $data['password'];
    
    // Check user credentials
    $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        send_error('Invalid username or password', 401, 'INVALID_CREDENTIALS');
    }
    
    $user = $result->fetch_assoc();
    
    // Verify password
    if (!password_verify($password, $user['password'])) {
        send_error('Invalid username or password', 401, 'INVALID_CREDENTIALS');
    }
    
    // Generate API token
    $token = generate_api_token($user['id'], $conn);
    
    // Send response with token
    send_response([
        'status' => 'success',
        'message' => 'Login successful',
        'data' => [
            'user_id' => $user['id'],
            'username' => $user['username'],
            'role' => $user['role'],
            'token' => $token
        ]
    ]);
}

// Logout handler
function handle_logout($conn) {
    // Only allow GET or POST for logout
    if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        send_error('Method not allowed', 405, 'METHOD_NOT_ALLOWED');
    }
    
    // Get token
    $token = get_api_token();
    
    if ($token) {
        // Delete token from database
        $stmt = $conn->prepare("DELETE FROM api_tokens WHERE token = ?");
        $stmt->bind_param("s", $token);
        $stmt->execute();
    }
    
    send_response([
        'status' => 'success',
        'message' => 'Logout successful'
    ]);
}
