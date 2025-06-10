<?php
/**
 * User Login Page
 * 
 * Handles user authentication and session management.
 * Redirects authenticated users and validates login credentials.
 */

session_start();
require_once 'config/db.php';
require_once 'config/activity_logger.php';

// Initialize activity logger for tracking user login attempts
$activityLogger = new UserActivityLogger($conn);

// Redirect already authenticated users to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

// Process login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    // Query database for user credentials
    $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
      if ($user = $result->fetch_assoc()) {
        // Verify the provided password against stored hash
        if (password_verify($password, $user['password'])) {
            // Create user session variables for authenticated user
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['last_activity'] = time();
            
            // Log successful login activity for security tracking
            $activityLogger->logLogin($user['id'], $user['username'], $user['role']);
            
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Invalid password";
        }
    } else {
        $error = "User not found";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Inventory Management System</title>
    <link rel="stylesheet" href="css/style.css">    <style>
        /* Login page styling */
        body {
            font-family: Arial, sans-serif;
            background-color: #f0f0f0;
            margin: 0;
            padding: 20px;
        }
        
        .login-container {
            max-width: 400px;
            margin: 50px auto;
            background: white;
            padding: 30px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .login-header h1 {
            color: #333;
            text-align: center;
            margin-bottom: 10px;
        }
        
        .login-header p {
            color: #666;
            text-align: center;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin: 15px 0;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 3px;
            font-size: 16px;
        }
        
        .btn {
            width: 100%;
            padding: 12px;
            background-color: #0066cc;
            color: white;
            border: none;
            border-radius: 3px;
            font-size: 16px;
            cursor: pointer;
        }
        
        .btn:hover {
            background-color: #0052a3;
        }
        
        .error-message {
            background-color: #ffcccc;
            color: #cc0000;
            padding: 10px;
            border-radius: 3px;
            margin: 10px 0;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="login-container">
            <div class="login-box">
                <div class="login-header">
                    <h1>Welcome Back!</h1>
                    <p>Please login to your account</p>
                </div>
                <?php if (isset($error)): ?>
                    <div class="error-message"><?php echo $error; ?></div>
                <?php endif; ?>
                <form method="POST" class="login-form">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" placeholder="Enter your username" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" placeholder="Enter your password" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="login-button">Sign In</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
