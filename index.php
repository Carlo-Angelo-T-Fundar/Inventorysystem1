<?php
require_once 'config/db.php';
require_once 'config/auth.php';

// Check if user is admin
$is_admin = false;
$stmt = $conn->prepare("SELECT username FROM users WHERE id = ? AND username = 'admin'");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    $is_admin = true;
}

// Page title for header
$page_title = "Dashboard";
include 'templates/header.php';
?>

<div class="content">
    <div class="content-header">
        <h1><i class="fas fa-home"></i> Dashboard</h1>
    </div>

    <div class="card">
        <div class="card-body">
            <h2>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h2>
            <?php if ($is_admin): ?>
            <div class="admin-actions">
                <a href="admin/manage_users.php" class="btn primary-btn">
                    <i class="fas fa-users"></i> Manage Users
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'templates/footer.php'; ?>
