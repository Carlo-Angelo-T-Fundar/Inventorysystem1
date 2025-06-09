<?php
// User management page - CRUD operations
// This file handles creating, reading, updating and deleting users

require_once 'config/db.php';
require_once 'config/auth.php';

// Only admins can access this page
requireRole(['admin'], $conn, 'index.php');

$error = '';
$success = '';
$action = $_GET['action'] ?? 'list'; // default to showing list
$user_id = $_GET['id'] ?? null;

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    
    if ($action === 'create') {
        // Get form data
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $email = trim($_POST['email']);
        $role = $_POST['role'];
        $full_name = trim($_POST['full_name']);
        
        // Basic validation - learned this in web dev class
        if (empty($username) || empty($password) || empty($email) || empty($role) || empty($full_name)) {
            $error = "All fields are required";
        } elseif (strlen($password) < 6) {
            $error = "Password must be at least 6 characters long";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format";
        } elseif (!in_array($role, ['admin', 'store_clerk', 'cashier'])) {
            $error = "Invalid role selected";
        } else {
            // Check if username exists already
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $error = "Username already exists";
            } else {
                // Check if email exists already  
                $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    $error = "Email already registered";
                } else {
                    // Hash the password for security
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Check if we need to add full_name column (might not exist yet)
                    $check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'full_name'");
                    if ($check_column->num_rows == 0) {
                        $alter_sql = "ALTER TABLE users ADD COLUMN full_name VARCHAR(100) AFTER email";
                        $conn->query($alter_sql);
                    }
                    
                    // Insert new user
                    $stmt = $conn->prepare("INSERT INTO users (username, password, email, full_name, role) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssss", $username, $hashed_password, $email, $full_name, $role);
                    
                    if ($stmt->execute()) {
                        $success = "User created successfully!";
                        $action = 'list'; // go back to list
                    } else {
                        $error = "Error creating user: " . $conn->error;
                    }
                }
            }
        }
    } elseif ($action === 'update') {
        // Update existing user
        $user_id = $_POST['user_id'];
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $role = $_POST['role'];
        $full_name = trim($_POST['full_name']);
        $password = $_POST['password'];
        
        // Validate inputs
        if (empty($username) || empty($email) || empty($role) || empty($full_name)) {
            $error = "All fields except password are required";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format";
        } elseif (!in_array($role, ['admin', 'store_clerk', 'cashier'])) {
            $error = "Invalid role selected";
        } else {
            // Check username conflict with other users
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $stmt->bind_param("si", $username, $user_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $error = "Username already exists";
            } else {
                // Check email conflict with other users
                $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->bind_param("si", $email, $user_id);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    $error = "Email already registered";
                } else {                    // Update the user
                    if (!empty($password)) {
                        // Update with new password
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("UPDATE users SET username = ?, password = ?, email = ?, full_name = ?, role = ? WHERE id = ?");
                        $stmt->bind_param("sssssi", $username, $hashed_password, $email, $full_name, $role, $user_id);
                    } else {
                        // Update without changing password
                        $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, full_name = ?, role = ? WHERE id = ?");
                        $stmt->bind_param("ssssi", $username, $email, $full_name, $role, $user_id);
                    }
                    
                    if ($stmt->execute()) {
                        $success = "User updated successfully!";
                        $action = 'list'; // go back to list
                    } else {
                        $error = "Error updating user: " . $conn->error;
                    }
                }
            }
        }
    } elseif ($action === 'delete') {
        // Delete user
        $user_id = $_POST['user_id'];
        
        // Don't let them delete the main admin - that would be bad!
        $stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if ($user['username'] === 'admin') {
            $error = "Cannot delete the main admin user";
        } else {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            
            if ($stmt->execute()) {
                $success = "User deleted successfully!";
            } else {
                $error = "Error deleting user: " . $conn->error;
            }
        }
        $action = 'list'; // back to list
    }
}

// Get user for editing
if ($action === 'edit' && $user_id) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $edit_user = $stmt->get_result()->fetch_assoc();
    
    if (!$edit_user) {
        $error = "User not found";
        $action = 'list';
    }
}

// Get all users for the list
if ($action === 'list') {
    // Check if full_name column exists, if not add it
    $check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'full_name'");
    if ($check_column->num_rows == 0) {
        $alter_sql = "ALTER TABLE users ADD COLUMN full_name VARCHAR(100) AFTER email";
        $conn->query($alter_sql);
    }
    
    $result = $conn->query("SELECT id, username, email, full_name, role, created_at FROM users ORDER BY id ASC");
}

$page_title = "User Management";
$current_page = 'users_crud';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">    <title><?php echo $page_title; ?> - Inventory System</title>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap">
    <!-- our css files -->
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/sidebar.css">
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
        }        .sidebar .admin-avatar {
            width: 45px !important;
            height: 45px !important;
            background-color: #e9ecef !important;
            border-radius: 50% !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            border: 2px solid #0066cc !important;
            font-size: 22px !important;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1) !important;
            transition: all 0.2s ease !important;
            cursor: pointer !important;
            text-decoration: none !important;
            color: inherit !important;
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

        /* quick styling for this page */        .user-management-container {
            padding: 20px;
            max-width: 1200px;
        }

        /* simple button styles */
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 2px;
            font-size: 14px;
        }

        .btn-primary {
            background-color: #0066cc;
            color: white;
        }

        .btn-primary:hover {
            background-color: #0052a3;
        }

        .btn-secondary {
            background-color: #666;
            color: white;
        }

        .btn-secondary:hover {
            background-color: #444;
        }

        .btn-success {
            background-color: #28a745;
            color: white;
        }

        .btn-success:hover {
            background-color: #059669;
        }

        .btn-warning {
            background-color: #f59e0b;
            color: white;
        }        .btn-warning:hover {
            background-color: #e0a800;
        }

        .btn-danger {
            background-color: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background-color: #c82333;
        }

        .btn-sm {
            padding: 4px 8px;
            font-size: 12px;
        }

        /* simple card style */
        .card {
            background: white;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .card-header {
            background: #f8f9fa;
            color: black;
            padding: 15px;
            border-bottom: 1px solid #ddd;
        }

        .card-header h2 {
            margin: 0;
            font-size: 18px;
            font-weight: bold;
        }

        .card-content {
            padding: 20px;
        }        /* form layout */
        .form-grid {
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: black;
            font-weight: bold;
            font-size: 14px;
        }

        /* basic form inputs */
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 3px;
            font-size: 14px;
            box-sizing: border-box;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #0066cc;
        }

        .form-actions {
            margin-top: 20px;
        }

        /* basic table */
        .table-container {
            overflow-x: auto;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }        .table th,
        .table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .table th {
            background-color: #f8f9fa;
            font-weight: bold;
            color: black;
            font-size: 14px;
        }

        .table td {
            color: #333;
            font-size: 14px;
        }

        .table tbody tr:hover {
            background-color: #f5f5f5;
        }

        /* simple role badges */
        .role-badge {
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: bold;
        }

        .role-admin {
            background-color: #ffebee;
            color: #c62828;
        }

        .role-store_clerk {
            background-color: #e8f5e8;
            color: #2e7d2e;
        }        .role-cashier {
            background-color: #fff3e0;
            color: #ef6c00;
        }
          .actions {
            display: flex;
            gap: 5px;
        }
        
        /* alert messages */
        .alert {
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
            font-size: 14px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .delete-form {
            display: inline;
        }

        /* when no users found */
        .empty-state {
            text-align: center;
            padding: 50px;
            color: #666;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 10px;
            color: #ccc;
        }

        .stat-card .icon {
            width: 3rem;
            height: 3rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
            color: white;
        }

        .stat-card .number {
            font-size: 1.875rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }        .stat-card .label {
            color: #6b7280;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        /* mobile adjustments - basic stuff */
        @media (max-width: 768px) {
            .table {
                font-size: 12px;
            }
            
            .table th,
            .table td {
                padding: 5px;
            }
        }
    </style>
</head>
<body class="logged-in page-users_crud" data-page="users_crud">    <div class="dashboard-container">
        <!-- sidebar goes here -->
        <?php 
        $current_page = 'users_crud';
        require_once 'templates/sidebar.php'; 
        ?><!-- main page content -->
        <main class="main-content">
            <header class="dashboard-header">
                <h1>👥 User Management</h1>
                <div class="header-actions">
                    <?php if ($action !== 'list'): ?>
                        <a href="?action=list" class="btn btn-secondary">
                            ⬅️ Back to List
                        </a>
                    <?php endif; ?>
                    <?php if ($action === 'list'): ?>
                        <a href="?action=create" class="btn btn-primary">
                            ➕ Add New User
                        </a>
                    <?php endif; ?>
                </div>
            </header>

            <div class="user-management-container">

                <!-- show error or success messages -->
                <?php if (!empty($error)): ?>                <div class="alert alert-error">
                        ⚠️
                        <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success">
                        ✅
                        <strong>Success:</strong> <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <?php if ($action === 'list'): ?>
                    <!-- User Statistics -->
                    <?php                    $stats_query = $conn->query("
                        SELECT 
                            COUNT(*) as total_users,
                            SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admin_count,
                            SUM(CASE WHEN role = 'store_clerk' THEN 1 ELSE 0 END) as clerk_count,
                            SUM(CASE WHEN role = 'cashier' THEN 1 ELSE 0 END) as cashier_count
                        FROM users
                    ");
                    $stats = $stats_query->fetch_assoc();
                    ?>
                    <div class="stats-grid">                        <div class="stat-card">
                            <div class="icon" style="background-color: #3b82f6;">
                                👥
                            </div>
                            <div class="number"><?php echo $stats['total_users']; ?></div>
                            <div class="label">Total Users</div>
                        </div>
                        <div class="stat-card">
                            <div class="icon" style="background-color: #ef4444;">
                                🛡️
                            </div>
                            <div class="number"><?php echo $stats['admin_count']; ?></div>
                            <div class="label">Admins</div>
                        </div>
                        <div class="stat-card">
                            <div class="icon" style="background-color: #f59e0b;">
                                💼
                            </div>
                            <div class="number"><?php echo $stats['cashier_count'] + $stats['clerk_count']; ?></div>
                            <div class="label">Staff</div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($action === 'create' || $action === 'edit'): ?>
                    <!-- User Form -->
                    <div class="card">                        <div class="card-header">
                            <h2>
                                <?php echo $action === 'create' ? '➕' : '✏️'; ?>
                                <?php echo $action === 'create' ? 'Add New User' : 'Edit User'; ?>
                            </h2>
                        </div>
                        <div class="card-content">
                            <form method="POST">
                                <input type="hidden" name="action" value="<?php echo $action === 'create' ? 'create' : 'update'; ?>">
                                <?php if ($action === 'edit'): ?>
                                    <input type="hidden" name="user_id" value="<?php echo $edit_user['id']; ?>">
                                <?php endif; ?>
                                
                                <div class="form-grid">                                    <div class="form-group">
                                        <label for="full_name">
                                            🆔 Full Name
                                        </label>
                                        <input type="text" id="full_name" name="full_name" 
                                               placeholder="Enter full name"
                                               value="<?php echo $action === 'edit' ? htmlspecialchars($edit_user['full_name'] ?? '') : ''; ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="username">
                                            👤 Username
                                        </label>
                                        <input type="text" id="username" name="username" 
                                               placeholder="Enter username"
                                               value="<?php echo $action === 'edit' ? htmlspecialchars($edit_user['username']) : ''; ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="email">
                                            📧 Email Address
                                        </label>
                                        <input type="email" id="email" name="email" 
                                               placeholder="Enter email address"
                                               value="<?php echo $action === 'edit' ? htmlspecialchars($edit_user['email']) : ''; ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="role">
                                            🏷️ User Role
                                        </label><select id="role" name="role" required>
                                            <option value="">Select Role</option>
                                            <option value="admin" <?php echo ($action === 'edit' && $edit_user['role'] === 'admin') ? 'selected' : ''; ?>>
                                                Admin - Overall Operations & System Management
                                            </option>
                                            <option value="store_clerk" <?php echo ($action === 'edit' && $edit_user['role'] === 'store_clerk') ? 'selected' : ''; ?>>
                                                Store Clerk - Product Availability Control
                                            </option>
                                            <option value="cashier" <?php echo ($action === 'edit' && $edit_user['role'] === 'cashier') ? 'selected' : ''; ?>>
                                                Cashier - Sales Operations & Order Processing
                                            </option>
                                        </select>
                                    </div>
                                      <div class="form-group">
                                        <label for="password">
                                            🔒 Password 
                                            <?php echo $action === 'edit' ? '(leave blank to keep current)' : ''; ?>
                                        </label>
                                        <input type="password" id="password" name="password" 
                                               placeholder="<?php echo $action === 'edit' ? 'Enter new password or leave blank' : 'Enter password'; ?>"
                                               <?php echo $action === 'create' ? 'required' : ''; ?>>
                                    </div>
                                </div>
                                  <div class="form-actions">
                                    <button type="submit" class="btn btn-<?php echo $action === 'create' ? 'success' : 'primary'; ?>">
                                        <?php echo $action === 'create' ? '➕' : '💾'; ?>
                                        <?php echo $action === 'create' ? 'Create User' : 'Update User'; ?>
                                    </button>
                                    <a href="?action=list" class="btn btn-secondary">
                                        ❌ Cancel
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($action === 'list'): ?>
                    <!-- Users Table -->
                    <div class="card">                        <div class="card-header">
                            <h2>
                                📋 
                                All Users (<?php echo $result ? $result->num_rows : 0; ?>)
                            </h2>
                        </div>
                        <div class="card-content">
                            <?php if ($result && $result->num_rows > 0): ?>
                                <div class="table-container">
                                    <table class="table">                                        <thead>
                                            <tr>
                                                <th>#️⃣ ID</th>
                                                <th>🆔 Full Name</th>
                                                <th>👤 Username</th>
                                                <th>📧 Email</th>
                                                <th>🏷️ Role</th>
                                                <th>📅 Created</th>
                                                <th>⚙️ Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>                                            <?php while($row = $result->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($row['id']); ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($row['full_name'] ?? $row['username']); ?></strong>
                                                </td>
                                                <td><?php echo htmlspecialchars($row['username']); ?></td>
                                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                                <td>
                                                    <span class="role-badge role-<?php echo $row['role']; ?>">
                                                        <?php                                                        $role_labels = [
                                                            'admin' => 'Admin',
                                                            'store_clerk' => 'Store Clerk',
                                                            'cashier' => 'Cashier'
                                                        ];
                                                        echo $role_labels[$row['role']] ?? ucfirst($row['role']);
                                                        ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M j, Y', strtotime($row['created_at'])); ?></td>
                                                <td>
                                                    <div class="actions">                                                        <a href="?action=edit&id=<?php echo $row['id']; ?>" class="btn btn-warning btn-sm" title="Edit User">
                                                            ✏️
                                                        </a>
                                                        <?php if ($row['username'] !== 'admin'): ?>
                                                            <form method="POST" class="delete-form" onsubmit="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
                                                                <input type="hidden" name="action" value="delete">
                                                                <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                                                <button type="submit" class="btn btn-danger btn-sm" title="Delete User">
                                                                    🗑️
                                                                </button>
                                                            </form>
                                                        <?php else: ?>
                                                            <span class="btn btn-secondary btn-sm" style="opacity: 0.5; cursor: not-allowed;" title="Cannot delete main admin">
                                                                🛡️
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>                                <div class="empty-state">
                                    👥
                                    <h3>No Users Found</h3>
                                    <p>Get started by creating your first user account.</p>
                                    <a href="?action=create" class="btn btn-primary">
                                        ➕ Create First User
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Auto-logout system -->    <script>
        // Mark body as logged in for auto-logout detection
        document.body.classList.add('logged-in');
        document.body.setAttribute('data-user-id', '<?php echo $_SESSION['user_id']; ?>');
    </script>
</body>
</html>


