<?php
require_once 'config/db.php';
require_once 'config/auth.php';

// Initialize variables
$success_message = '';
$error_message = '';

// Get user data
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT username, email, role, created_at FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    header('Location: login.php');
    exit();
}

// Get user role for display
$user_role = getCurrentUserRole($conn);

// If user submits form to update profile settings
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'update_profile') {
            // Update email
            $new_email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
            if ($new_email) {
                // Check if email already exists for another user
                $check_email = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $check_email->bind_param("si", $new_email, $user_id);
                $check_email->execute();
                $email_result = $check_email->get_result();
                
                if ($email_result->num_rows > 0) {
                    $error_message = "Email address is already in use by another account";
                } else {
                    $update = $conn->prepare("UPDATE users SET email = ? WHERE id = ?");
                    $update->bind_param("si", $new_email, $user_id);
                    if ($update->execute()) {
                        $success_message = "Profile updated successfully";
                        $user['email'] = $new_email;
                    } else {
                        $error_message = "Failed to update profile";
                    }
                }
            } else {
                $error_message = "Invalid email format";
            }
        }
    }
}

$page_title = "Profile";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Inventory System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/sidebar.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- Include Sidebar -->
        <?php 
        $current_page = 'profile';
        require_once 'templates/sidebar.php'; 
        ?>        <!-- Main Content -->
        <main class="main-content">
            <header class="dashboard-header">
                <h1>Profile Settings</h1>
            </header>
            <!-- Alert Messages -->
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="close-btn" onclick="this.parentElement.style.display='none'">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="close-btn" onclick="this.parentElement.style.display='none'">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            <?php endif; ?>
            
            <!-- Profile Content -->
            <div class="profile-container">
                <div class="profile-main">
                    <!-- Profile Information Card -->
                    <div class="card">
                        <div class="card-header">
                            <h2><i class="fas fa-user"></i> Profile Information</h2>
                        </div>
                        <div class="card-body">
                            <form method="POST" class="profile-form">
                                <input type="hidden" name="action" value="update_profile">
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="username">Username</label>
                                        <input type="text" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                                        <small class="form-text">Username cannot be changed.</small>
                                    </div>
                                    <div class="form-group">
                                        <label for="role">Role</label>
                                        <input type="text" id="role" value="<?php echo ucfirst(htmlspecialchars($user['role'])); ?>" disabled>
                                        <small class="form-text">Role is assigned by administrators.</small>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="email">Email Address</label>
                                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                                    <small class="form-text">Your email address for notifications and account recovery.</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="created_at">Member Since</label>
                                    <input type="text" id="created_at" value="<?php echo date('F j, Y', strtotime($user['created_at'])); ?>" disabled>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Profile
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Account Security Card -->
                    <div class="card">
                        <div class="card-header">
                            <h2><i class="fas fa-shield-alt"></i> Account Security</h2>
                        </div>
                        <div class="card-body">
                            <div class="security-section">
                                <div class="security-info">
                                    <h3>Password Security</h3>
                                    <p>Regularly changing your password helps keep your account secure. Use a strong password with at least 8 characters.</p>
                                </div>
                                <div class="security-action">
                                    <a href="change-password.php" class="btn btn-warning">
                                        <i class="fas fa-key"></i> Change Password
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Profile Summary Sidebar -->
                <div class="profile-sidebar">
                    <div class="card">
                        <div class="card-header">
                            <h2><i class="fas fa-info-circle"></i> Account Summary</h2>
                        </div>
                        <div class="card-body">
                            <div class="profile-summary">
                                <div class="avatar-section">
                                    <div class="user-avatar large">
                                        <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                    </div>
                                    <h3><?php echo htmlspecialchars($user['username']); ?></h3>
                                    <span class="role-badge <?php echo $user['role']; ?>">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </div>
                                
                                <div class="profile-details">
                                    <div class="detail-item">
                                        <span class="detail-label">Email:</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($user['email'] ?? 'Not set'); ?></span>
                                    </div>
                                    
                                    <div class="detail-item">
                                        <span class="detail-label">Member since:</span>
                                        <span class="detail-value"><?php echo date('M Y', strtotime($user['created_at'])); ?></span>
                                    </div>
                                    
                                    <div class="detail-item">
                                        <span class="detail-label">Account Status:</span>
                                        <span class="status-badge active">Active</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

<style>
/* Profile-specific styles */
.profile-container {
    display: grid;
    grid-template-columns: 1fr 350px;
    gap: 20px;
    margin-top: 20px;
}

.profile-main {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.profile-sidebar {
    display: flex;
    flex-direction: column;
}

.profile-form {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.form-group label {
    font-weight: 600;
    color: #374151;
    font-size: 14px;
}

.form-group input {
    padding: 12px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 14px;
    transition: border-color 0.2s;
}

.form-group input:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-group input:disabled {
    background-color: #f9fafb;
    color: #6b7280;
    cursor: not-allowed;
}

.form-text {
    font-size: 12px;
    color: #6b7280;
}

.security-section {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.security-info h3 {
    margin: 0 0 8px 0;
    font-size: 16px;
    font-weight: 600;
    color: #374151;
}

.security-info p {
    margin: 0;
    color: #6b7280;
    font-size: 14px;
}

.profile-summary {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.avatar-section {
    text-align: center;
    padding-bottom: 20px;
    border-bottom: 1px solid #e5e7eb;
}

.user-avatar.large {
    width: 80px;
    height: 80px;
    font-size: 2rem;
    margin: 0 auto 15px;
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
}

.avatar-section h3 {
    margin: 0 0 10px 0;
    font-size: 18px;
    font-weight: 600;
    color: #111827;
}

.role-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.role-badge.admin {
    background-color: #fee2e2;
    color: #dc2626;
}

.role-badge.cashier {
    background-color: #dcfce7;
    color: #16a34a;
}

.role-badge.user {
    background-color: #dbeafe;
    color: #2563eb;
}

.profile-details {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.detail-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.detail-label {
    font-size: 12px;
    color: #6b7280;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.detail-value {
    font-size: 14px;
    color: #111827;
    font-weight: 500;
}

.status-badge.active {
    padding: 4px 8px;
    background-color: #dcfce7;
    color: #16a34a;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    display: inline-block;
    width: fit-content;
}

.btn.btn-warning {
    background-color: #f59e0b;
    color: white;
    border: none;
}

.btn.btn-warning:hover {
    background-color: #d97706;
    transform: translateY(-1px);
}

.alert {
    padding: 12px 16px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    position: relative;
}

.alert.alert-success {
    background-color: #dcfce7;
    color: #166534;
    border-left: 4px solid #16a34a;
}

.alert.alert-error {
    background-color: #fee2e2;
    color: #991b1b;
    border-left: 4px solid #dc2626;
}

.close-btn {
    position: absolute;
    right: 12px;
    background: none;
    border: none;
    cursor: pointer;
    color: inherit;
    font-size: 14px;
    opacity: 0.7;
}

.close-btn:hover {
    opacity: 1;
}

@media (max-width: 768px) {
    .profile-container {
        grid-template-columns: 1fr;
        gap: 15px;
    }
    
    .form-row {
        grid-template-columns: 1fr;
        gap: 15px;
    }
    
    .security-section {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
}
</style>

<script>
// Auto-hide alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            setTimeout(function() {
                alert.style.display = 'none';
            }, 300);
        }, 5000);
    });
});

// Form validation
document.getElementById('email').addEventListener('blur', function() {
    const email = this.value;
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    
    if (email && !emailRegex.test(email)) {
        this.style.borderColor = '#dc2626';
        if (!this.nextElementSibling || !this.nextElementSibling.classList.contains('error-message')) {
            const error = document.createElement('span');
            error.className = 'error-message';
            error.style.color = '#dc2626';
            error.style.fontSize = '12px';
            error.style.marginTop = '4px';
            error.textContent = 'Please enter a valid email address';
            this.parentNode.appendChild(error);
        }
    } else {
        this.style.borderColor = '#d1d5db';
        const error = this.parentNode.querySelector('.error-message');
        if (error) {
            error.remove();
        }
    }
});
</script>

</body>
</html>
