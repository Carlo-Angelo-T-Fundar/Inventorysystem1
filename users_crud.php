<?php
session_start();
require_once 'config/db.php';
require_once 'config/auth.php';

// Check if user is admin
requireRole(['admin'], $conn, 'index.php');

$error = '';
$success = '';
$action = $_GET['action'] ?? 'list';
$user_id = $_GET['id'] ?? null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    
    if ($action === 'create') {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $email = trim($_POST['email']);
        $role = $_POST['role'];
        $full_name = trim($_POST['full_name']);
        
        // Validation
        if (empty($username) || empty($password) || empty($email) || empty($role) || empty($full_name)) {
            $error = "All fields are required";
        } elseif (strlen($password) < 6) {
            $error = "Password must be at least 6 characters long";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format";
        } elseif (!in_array($role, ['admin', 'supplier', 'store_clerk', 'cashier'])) {
            $error = "Invalid role selected";
        } else {
            // Check if username already exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $error = "Username already exists";
            } else {
                // Check if email already exists
                $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    $error = "Email already registered";
                } else {
                    // Create new user
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Check if full_name column exists
                    $check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'full_name'");
                    if ($check_column->num_rows == 0) {
                        $alter_sql = "ALTER TABLE users ADD COLUMN full_name VARCHAR(100) AFTER email";
                        $conn->query($alter_sql);
                    }
                    
                    $stmt = $conn->prepare("INSERT INTO users (username, password, email, full_name, role) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssss", $username, $hashed_password, $email, $full_name, $role);
                    
                    if ($stmt->execute()) {
                        $success = "User created successfully!";
                        $action = 'list'; // Redirect to list view
                    } else {
                        $error = "Error creating user: " . $conn->error;
                    }
                }
            }
        }
    } elseif ($action === 'update') {
        $user_id = $_POST['user_id'];
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $role = $_POST['role'];
        $full_name = trim($_POST['full_name']);
        $password = $_POST['password'];
        
        // Validation
        if (empty($username) || empty($email) || empty($role) || empty($full_name)) {
            $error = "All fields except password are required";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format";
        } elseif (!in_array($role, ['admin', 'supplier', 'store_clerk', 'cashier'])) {
            $error = "Invalid role selected";
        } else {
            // Check if username already exists for other users
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $stmt->bind_param("si", $username, $user_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $error = "Username already exists";
            } else {
                // Check if email already exists for other users
                $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->bind_param("si", $email, $user_id);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    $error = "Email already registered";
                } else {
                    // Update user
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
                        $action = 'list'; // Redirect to list view
                    } else {
                        $error = "Error updating user: " . $conn->error;
                    }
                }
            }
        }
    } elseif ($action === 'delete') {
        $user_id = $_POST['user_id'];
        
        // Prevent deleting the main admin user
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
        $action = 'list'; // Redirect to list view
    }
}

// Get user data for edit form
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

// Get list of all users for display
if ($action === 'list') {
    $result = $conn->query("SELECT id, username, email, full_name, role, created_at FROM users ORDER BY id ASC");
}

$page_title = "User Management";
$current_page = 'users_crud';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Inventory Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <style>
        .user-management-container {
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #e5e7eb;
        }

        .content-header h1 {
            color: #1f2937;
            font-size: 1.875rem;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .header-actions {
            display: flex;
            gap: 1rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
            font-size: 0.875rem;
        }

        .btn-primary {
            background-color: #3b82f6;
            color: white;
        }

        .btn-primary:hover {
            background-color: #2563eb;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background-color: #6b7280;
            color: white;
        }

        .btn-secondary:hover {
            background-color: #4b5563;
        }

        .btn-success {
            background-color: #10b981;
            color: white;
        }

        .btn-success:hover {
            background-color: #059669;
        }

        .btn-warning {
            background-color: #f59e0b;
            color: white;
        }

        .btn-warning:hover {
            background-color: #d97706;
        }

        .btn-danger {
            background-color: #ef4444;
            color: white;
        }

        .btn-danger:hover {
            background-color: #dc2626;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.75rem;
        }

        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .card-header h2 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-content {
            padding: 2rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .form-group {
            margin-bottom: 0;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #374151;
            font-weight: 500;
            font-size: 0.875rem;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.875rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 0.875rem;
            transition: border-color 0.2s, box-shadow 0.2s;
            box-sizing: border-box;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-actions {
            margin-top: 2rem;
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }

        .table-container {
            overflow-x: auto;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }

        .table th,
        .table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }

        .table th {
            background-color: #f8fafc;
            font-weight: 600;
            color: #374151;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .table td {
            color: #6b7280;
            font-size: 0.875rem;
        }

        .table tbody tr:hover {
            background-color: #f9fafb;
        }

        .role-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .role-admin {
            background-color: #fee2e2;
            color: #dc2626;
        }

        .role-supplier {
            background-color: #dbeafe;
            color: #2563eb;
        }

        .role-store_clerk {
            background-color: #d1fae5;
            color: #059669;
        }

        .role-cashier {
            background-color: #fef3c7;
            color: #d97706;
        }

        .actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-success {
            background-color: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-error {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .delete-form {
            display: inline;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #6b7280;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #d1d5db;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            text-align: center;
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
        }

        .stat-card .label {
            color: #6b7280;
            font-size: 0.875rem;
            font-weight: 500;
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .content-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }
            
            .header-actions {
                width: 100%;
                justify-content: flex-start;
            }
            
            .table {
                font-size: 0.75rem;
            }
            
            .table th,
            .table td {
                padding: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">        <!-- Include Sidebar -->
        <?php require_once 'templates/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <div class="user-management-container">
                <!-- Content Header -->
                <div class="content-header">
                    <h1>
                        <i class="fas fa-users-cog"></i> 
                        User Management
                    </h1>
                    <div class="header-actions">
                        <?php if ($action !== 'list'): ?>
                            <a href="?action=list" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to List
                            </a>
                        <?php endif; ?>
                        <?php if ($action === 'list'): ?>
                            <a href="?action=create" class="btn btn-primary">
                                <i class="fas fa-user-plus"></i> Add New User
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Alert Messages -->
                <?php if (!empty($error)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <?php if ($action === 'list'): ?>
                    <!-- User Statistics -->
                    <?php
                    $stats_query = $conn->query("
                        SELECT 
                            COUNT(*) as total_users,
                            SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admin_count,
                            SUM(CASE WHEN role = 'supplier' THEN 1 ELSE 0 END) as supplier_count,
                            SUM(CASE WHEN role = 'store_clerk' THEN 1 ELSE 0 END) as clerk_count,
                            SUM(CASE WHEN role = 'cashier' THEN 1 ELSE 0 END) as cashier_count
                        FROM users
                    ");
                    $stats = $stats_query->fetch_assoc();
                    ?>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="icon" style="background-color: #3b82f6;">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="number"><?php echo $stats['total_users']; ?></div>
                            <div class="label">Total Users</div>
                        </div>
                        <div class="stat-card">
                            <div class="icon" style="background-color: #ef4444;">
                                <i class="fas fa-user-shield"></i>
                            </div>
                            <div class="number"><?php echo $stats['admin_count']; ?></div>
                            <div class="label">Admins</div>
                        </div>
                        <div class="stat-card">
                            <div class="icon" style="background-color: #10b981;">
                                <i class="fas fa-truck"></i>
                            </div>
                            <div class="number"><?php echo $stats['supplier_count']; ?></div>
                            <div class="label">Suppliers</div>
                        </div>
                        <div class="stat-card">
                            <div class="icon" style="background-color: #f59e0b;">
                                <i class="fas fa-cash-register"></i>
                            </div>
                            <div class="number"><?php echo $stats['cashier_count'] + $stats['clerk_count']; ?></div>
                            <div class="label">Staff</div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($action === 'create' || $action === 'edit'): ?>
                    <!-- User Form -->
                    <div class="card">
                        <div class="card-header">
                            <h2>
                                <i class="fas fa-<?php echo $action === 'create' ? 'user-plus' : 'user-edit'; ?>"></i>
                                <?php echo $action === 'create' ? 'Add New User' : 'Edit User'; ?>
                            </h2>
                        </div>
                        <div class="card-content">
                            <form method="POST">
                                <input type="hidden" name="action" value="<?php echo $action === 'create' ? 'create' : 'update'; ?>">
                                <?php if ($action === 'edit'): ?>
                                    <input type="hidden" name="user_id" value="<?php echo $edit_user['id']; ?>">
                                <?php endif; ?>
                                
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="full_name">
                                            <i class="fas fa-id-card"></i> Full Name
                                        </label>
                                        <input type="text" id="full_name" name="full_name" 
                                               placeholder="Enter full name"
                                               value="<?php echo $action === 'edit' ? htmlspecialchars($edit_user['full_name'] ?? '') : ''; ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="username">
                                            <i class="fas fa-user"></i> Username
                                        </label>
                                        <input type="text" id="username" name="username" 
                                               placeholder="Enter username"
                                               value="<?php echo $action === 'edit' ? htmlspecialchars($edit_user['username']) : ''; ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="email">
                                            <i class="fas fa-envelope"></i> Email Address
                                        </label>
                                        <input type="email" id="email" name="email" 
                                               placeholder="Enter email address"
                                               value="<?php echo $action === 'edit' ? htmlspecialchars($edit_user['email']) : ''; ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="role">
                                            <i class="fas fa-user-tag"></i> User Role
                                        </label>
                                        <select id="role" name="role" required>
                                            <option value="">Select Role</option>
                                            <option value="admin" <?php echo ($action === 'edit' && $edit_user['role'] === 'admin') ? 'selected' : ''; ?>>
                                                Admin - Overall Operations & System Management
                                            </option>
                                            <option value="supplier" <?php echo ($action === 'edit' && $edit_user['role'] === 'supplier') ? 'selected' : ''; ?>>
                                                Supplier - Resupply & Inventory Restocking
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
                                            <i class="fas fa-lock"></i> Password 
                                            <?php echo $action === 'edit' ? '(leave blank to keep current)' : ''; ?>
                                        </label>
                                        <input type="password" id="password" name="password" 
                                               placeholder="<?php echo $action === 'edit' ? 'Enter new password or leave blank' : 'Enter password'; ?>"
                                               <?php echo $action === 'create' ? 'required' : ''; ?>>
                                    </div>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="submit" class="btn btn-<?php echo $action === 'create' ? 'success' : 'primary'; ?>">
                                        <i class="fas fa-<?php echo $action === 'create' ? 'plus' : 'save'; ?>"></i>
                                        <?php echo $action === 'create' ? 'Create User' : 'Update User'; ?>
                                    </button>
                                    <a href="?action=list" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> Cancel
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($action === 'list'): ?>
                    <!-- Users Table -->
                    <div class="card">
                        <div class="card-header">
                            <h2>
                                <i class="fas fa-list"></i> 
                                All Users (<?php echo $result ? $result->num_rows : 0; ?>)
                            </h2>
                        </div>
                        <div class="card-content">
                            <?php if ($result && $result->num_rows > 0): ?>
                                <div class="table-container">
                                    <table class="table">                                        <thead>
                                            <tr>
                                                <th><i class="fas fa-hashtag"></i> ID</th>
                                                <th><i class="fas fa-id-card"></i> Full Name</th>
                                                <th><i class="fas fa-user"></i> Username</th>
                                                <th><i class="fas fa-envelope"></i> Email</th>
                                                <th><i class="fas fa-user-tag"></i> Role</th>
                                                <th><i class="fas fa-calendar"></i> Created</th>
                                                <th><i class="fas fa-cogs"></i> Actions</th>
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
                                                        <?php 
                                                        $role_labels = [
                                                            'admin' => 'Admin',
                                                            'supplier' => 'Supplier', 
                                                            'store_clerk' => 'Store Clerk',
                                                            'cashier' => 'Cashier'
                                                        ];
                                                        echo $role_labels[$row['role']] ?? ucfirst($row['role']);
                                                        ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M j, Y', strtotime($row['created_at'])); ?></td>
                                                <td>
                                                    <div class="actions">
                                                        <a href="?action=edit&id=<?php echo $row['id']; ?>" class="btn btn-warning btn-sm" title="Edit User">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <?php if ($row['username'] !== 'admin'): ?>
                                                            <form method="POST" class="delete-form" onsubmit="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
                                                                <input type="hidden" name="action" value="delete">
                                                                <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                                                <button type="submit" class="btn btn-danger btn-sm" title="Delete User">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </form>
                                                        <?php else: ?>
                                                            <span class="btn btn-secondary btn-sm" style="opacity: 0.5; cursor: not-allowed;" title="Cannot delete main admin">
                                                                <i class="fas fa-shield-alt"></i>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-users"></i>
                                    <h3>No Users Found</h3>
                                    <p>Get started by creating your first user account.</p>
                                    <a href="?action=create" class="btn btn-primary">
                                        <i class="fas fa-user-plus"></i> Create First User
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
