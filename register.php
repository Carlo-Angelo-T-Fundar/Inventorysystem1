<?php
session_start();
require_once 'config/db.php';

// If user is already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    $full_name = trim($_POST['full_name']);
    
    // Validation
    if (empty($username) || empty($password) || empty($confirm_password) || empty($email) || empty($role) || empty($full_name)) {
        $error = "All fields are required";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
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
                
                // First, check if we need to add full_name column
                $check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'full_name'");
                if ($check_column->num_rows == 0) {
                    $alter_sql = "ALTER TABLE users ADD COLUMN full_name VARCHAR(100) AFTER email";
                    $conn->query($alter_sql);
                }
                
                $stmt = $conn->prepare("INSERT INTO users (username, password, email, full_name, role) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $username, $hashed_password, $email, $full_name, $role);
                
                if ($stmt->execute()) {
                    $success = "Account created successfully! You can now login.";
                    // Clear form data
                    $username = $email = $full_name = $role = '';
                } else {
                    $error = "Error creating account: " . $conn->error;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Inventory Management System</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        .register-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
        }
        
        .register-box {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 500px;
        }
        
        .register-header h1 {
            color: #333;
            margin-bottom: 10px;
            font-weight: 600;
            text-align: center;
        }
        
        .register-header p {
            color: #666;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        
        .input-with-icon {
            position: relative;
        }
        
        .input-with-icon svg {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
        }
        
        .input-with-icon input,
        .input-with-icon select {
            width: 100%;
            padding: 15px 15px 15px 45px;
            border: 2px solid #e1e1e1;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
            box-sizing: border-box;
        }
        
        .input-with-icon input:focus,
        .input-with-icon select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .role-selection {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .role-selection h3 {
            margin: 0 0 15px 0;
            color: #333;
            font-size: 18px;
        }
        
        .role-options {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        
        .role-option {
            border: 2px solid #e1e1e1;
            border-radius: 8px;
            padding: 15px;
            cursor: pointer;
            transition: all 0.3s;
            background: white;
        }
        
        .role-option:hover {
            border-color: #667eea;
            background: #f0f4ff;
        }
        
        .role-option.selected {
            border-color: #667eea;
            background: #667eea;
            color: white;
        }
        
        .role-option input[type="radio"] {
            display: none;
        }
        
        .role-title {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .role-description {
            font-size: 12px;
            opacity: 0.8;
        }
        
        .register-button {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .register-button:hover {
            transform: translateY(-2px);
        }
        
        .login-link {
            text-align: center;
            margin-top: 20px;
        }
        
        .login-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        
        .error-message, .success-message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-box">
            <div class="register-header">
                <h1>Create Account</h1>
                <p>Join our inventory management system</p>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <form method="POST" class="register-form">
                <div class="form-group">
                    <label for="full_name">Full Name</label>
                    <div class="input-with-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                        <input type="text" id="full_name" name="full_name" placeholder="Enter your full name" value="<?php echo isset($full_name) ? htmlspecialchars($full_name) : ''; ?>" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <div class="input-with-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="3"></circle>
                                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1 1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                            </svg>
                            <input type="text" id="username" name="username" placeholder="Choose username" value="<?php echo isset($username) ? htmlspecialchars($username) : ''; ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email</label>
                        <div class="input-with-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                <polyline points="22,6 12,13 2,6"></polyline>
                            </svg>
                            <input type="email" id="email" name="email" placeholder="your@email.com" value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>" required>
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="input-with-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                            </svg>
                            <input type="password" id="password" name="password" placeholder="Enter password" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <div class="input-with-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                            </svg>
                            <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm password" required>
                        </div>
                    </div>
                </div>
                
                <div class="role-selection">
                    <h3>Select Your Role</h3>
                    <div class="role-options">
                        <label class="role-option" onclick="selectRole('admin')">
                            <input type="radio" name="role" value="admin" <?php echo (isset($role) && $role === 'admin') ? 'checked' : ''; ?>>
                            <div class="role-title">Admin</div>
                            <div class="role-description">Overall operations & system management</div>
                        </label>
                        
                        <label class="role-option" onclick="selectRole('supplier')">
                            <input type="radio" name="role" value="supplier" <?php echo (isset($role) && $role === 'supplier') ? 'checked' : ''; ?>>
                            <div class="role-title">Supplier</div>
                            <div class="role-description">Resupply & inventory restocking</div>
                        </label>
                        
                        <label class="role-option" onclick="selectRole('store_clerk')">
                            <input type="radio" name="role" value="store_clerk" <?php echo (isset($role) && $role === 'store_clerk') ? 'checked' : ''; ?>>
                            <div class="role-title">Store Clerk</div>
                            <div class="role-description">Product availability control</div>
                        </label>
                        
                        <label class="role-option" onclick="selectRole('cashier')">
                            <input type="radio" name="role" value="cashier" <?php echo (isset($role) && $role === 'cashier') ? 'checked' : ''; ?>>
                            <div class="role-title">Cashier</div>
                            <div class="role-description">Sales operations & order processing</div>
                        </label>
                    </div>
                </div>
                
                <button type="submit" class="register-button">Create Account</button>
            </form>
            
            <div class="login-link">
                <p>Already have an account? <a href="login.php">Sign in here</a></p>
            </div>
        </div>
    </div>
    
    <script>
        function selectRole(role) {
            // Remove selected class from all options
            document.querySelectorAll('.role-option').forEach(option => {
                option.classList.remove('selected');
            });
            
            // Add selected class to clicked option
            event.currentTarget.classList.add('selected');
            
            // Check the radio button
            document.querySelector(`input[value="${role}"]`).checked = true;
        }
        
        // Set initial selection based on checked radio button
        document.addEventListener('DOMContentLoaded', function() {
            const checkedRadio = document.querySelector('input[name="role"]:checked');
            if (checkedRadio) {
                checkedRadio.closest('.role-option').classList.add('selected');
            }
        });
    </script>
</body>
</html>