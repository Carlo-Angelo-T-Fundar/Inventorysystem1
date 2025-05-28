<?php
// User Management API Endpoints
require_once 'config.php';

// Route handling
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

switch ($action) {
    case 'list':
        handle_list_users($conn);
        break;
    case 'create':
        handle_create_user($conn);
        break;
    case 'update':
        handle_update_user($conn);
        break;
    case 'delete':
        handle_delete_user($conn);
        break;
    case 'get':
        handle_get_user($conn);
        break;
    default:
        send_error('Invalid user management action', 404, 'INVALID_ENDPOINT');
}

// List all users
function handle_list_users($conn) {
    // Only allow GET for listing users
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        send_error('Method not allowed', 405, 'METHOD_NOT_ALLOWED');
    }
    
    // Require authentication
    $user_id = require_auth($conn);
    
    // Only admins can list users
    require_role($user_id, 'admin', $conn);
    
    // Get all users
    $result = $conn->query("SELECT id, username, email, role, created_at FROM users ORDER BY created_at DESC");
    
    $users = [];
    while ($row = $result->fetch_assoc()) {
        // Don't include password hash
        $users[] = [
            'id' => $row['id'],
            'username' => $row['username'],
            'email' => $row['email'],
            'role' => $row['role'],
            'created_at' => $row['created_at']
        ];
    }
    
    send_response([
        'status' => 'success',
        'data' => $users
    ]);
}

// Get a single user
function handle_get_user($conn) {
    // Only allow GET for getting a user
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        send_error('Method not allowed', 405, 'METHOD_NOT_ALLOWED');
    }
    
    // Require authentication
    $user_id = require_auth($conn);
    
    // Check if user ID is provided
    if (!isset($_GET['id'])) {
        send_error('User ID is required', 400, 'MISSING_USER_ID');
    }
    
    $target_user_id = (int) $_GET['id'];
    
    // Users can view their own profile, admins can view any profile
    if ($user_id !== $target_user_id) {
        require_role($user_id, 'admin', $conn);
    }
    
    // Get user
    $stmt = $conn->prepare("SELECT id, username, email, role, created_at FROM users WHERE id = ?");
    $stmt->bind_param("i", $target_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        send_error('User not found', 404, 'USER_NOT_FOUND');
    }
    
    $user = $result->fetch_assoc();
    
    send_response([
        'status' => 'success',
        'data' => $user
    ]);
}

// Create a new user
function handle_create_user($conn) {
    // Only allow POST for creating users
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        send_error('Method not allowed', 405, 'METHOD_NOT_ALLOWED');
    }
    
    // Require authentication
    $user_id = require_auth($conn);
    
    // Only admins can create users
    require_role($user_id, 'admin', $conn);
    
    // Get JSON data from request body
    $data = get_json_data();
    
    // Check required fields
    if (empty($data['username']) || empty($data['password']) || empty($data['role'])) {
        send_error('Username, password, and role are required', 400, 'MISSING_FIELDS');
    }
    
    $username = $data['username'];
    $password = $data['password'];
    $email = isset($data['email']) ? $data['email'] : '';
    $role = $data['role'];
    
    // Validate role
    $valid_roles = ['admin', 'cashier', 'store_clerk', 'supplier'];
    if (!in_array($role, $valid_roles)) {
        send_error('Invalid role. Must be one of: ' . implode(', ', $valid_roles), 400, 'INVALID_ROLE');
    }
    
    // Check if username already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        send_error('Username already exists', 409, 'USERNAME_EXISTS');
    }
    
    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Create user
    $stmt = $conn->prepare("INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $username, $hashed_password, $email, $role);
    
    if (!$stmt->execute()) {
        send_error('Failed to create user: ' . $conn->error, 500, 'DATABASE_ERROR');
    }
    
    $new_user_id = $conn->insert_id;
    
    send_response([
        'status' => 'success',
        'message' => 'User created successfully',
        'data' => [
            'id' => $new_user_id,
            'username' => $username,
            'email' => $email,
            'role' => $role
        ]
    ], 201);
}

// Update an existing user
function handle_update_user($conn) {
    // Only allow POST or PUT for updating users
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'PUT') {
        send_error('Method not allowed', 405, 'METHOD_NOT_ALLOWED');
    }
    
    // Require authentication
    $user_id = require_auth($conn);
    
    // Get JSON data from request body
    $data = get_json_data();
    
    // Check if user ID is provided
    $target_user_id = isset($_GET['id']) ? (int) $_GET['id'] : (isset($data['id']) ? (int) $data['id'] : null);
    
    if (!$target_user_id) {
        send_error('User ID is required', 400, 'MISSING_USER_ID');
    }
    
    // Users can update their own profile, admins can update any profile
    $is_admin = check_user_role($user_id, 'admin', $conn);
    $is_self_update = ($user_id === $target_user_id);
    
    if (!$is_admin && !$is_self_update) {
        send_error('You do not have permission to update this user', 403, 'ACCESS_DENIED');
    }
    
    // Get current user data
    $stmt = $conn->prepare("SELECT id, username, email, role FROM users WHERE id = ?");
    $stmt->bind_param("i", $target_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        send_error('User not found', 404, 'USER_NOT_FOUND');
    }
    
    $user = $result->fetch_assoc();
    
    // Prepare update data
    $username = isset($data['username']) ? $data['username'] : $user['username'];
    $email = isset($data['email']) ? $data['email'] : $user['email'];
    
    // Only admins can change roles
    $role = $user['role'];
    if ($is_admin && isset($data['role'])) {
        $valid_roles = ['admin', 'cashier', 'store_clerk', 'supplier'];
        if (!in_array($data['role'], $valid_roles)) {
            send_error('Invalid role. Must be one of: ' . implode(', ', $valid_roles), 400, 'INVALID_ROLE');
        }
        $role = $data['role'];
    }
    
    // Check if new username already exists
    if ($username !== $user['username']) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->bind_param("si", $username, $target_user_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            send_error('Username already exists', 409, 'USERNAME_EXISTS');
        }
    }
    
    // Update user
    if (isset($data['password'])) {
        // Update with password
        $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, role = ?, password = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $username, $email, $role, $hashed_password, $target_user_id);
    } else {
        // Update without password
        $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, role = ? WHERE id = ?");
        $stmt->bind_param("sssi", $username, $email, $role, $target_user_id);
    }
    
    if (!$stmt->execute()) {
        send_error('Failed to update user: ' . $conn->error, 500, 'DATABASE_ERROR');
    }
    
    send_response([
        'status' => 'success',
        'message' => 'User updated successfully',
        'data' => [
            'id' => $target_user_id,
            'username' => $username,
            'email' => $email,
            'role' => $role
        ]
    ]);
}

// Delete a user
function handle_delete_user($conn) {
    // Only allow DELETE or POST for deleting users
    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        send_error('Method not allowed', 405, 'METHOD_NOT_ALLOWED');
    }
    
    // Require authentication
    $user_id = require_auth($conn);
    
    // Only admins can delete users
    require_role($user_id, 'admin', $conn);
    
    // Check if user ID is provided
    $target_user_id = isset($_GET['id']) ? (int) $_GET['id'] : null;
    
    if (!$target_user_id) {
        // Try to get it from POST data if it's a POST request
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = get_json_data();
            $target_user_id = isset($data['id']) ? (int) $data['id'] : null;
        }
        
        if (!$target_user_id) {
            send_error('User ID is required', 400, 'MISSING_USER_ID');
        }
    }
    
    // Prevent deleting self
    if ($target_user_id === $user_id) {
        send_error('You cannot delete your own account', 400, 'CANNOT_DELETE_SELF');
    }
    
    // Delete user
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $target_user_id);
    
    if (!$stmt->execute()) {
        send_error('Failed to delete user: ' . $conn->error, 500, 'DATABASE_ERROR');
    }
    
    if ($stmt->affected_rows === 0) {
        send_error('User not found', 404, 'USER_NOT_FOUND');
    }
    
    send_response([
        'status' => 'success',
        'message' => 'User deleted successfully'
    ]);
}
