<?php
// header.php - this goes at the top of every page
// learned about includes in php class
require_once __DIR__ . '/../config/auth.php';

// check if user is admin - simple way
$is_admin = false;
$stmt = $conn->prepare("SELECT username FROM users WHERE id = ? AND username = 'admin'");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    $is_admin = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . " - " : ""; ?>Inventory System</title>
    <!-- basic styles instead of fancy external libraries -->
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/sidebar.css">
    
    <!-- auto logout thing -->
    <script src="css/auto-logout.js"></script>
</head>
<body class="logged-in" data-user-id="<?php echo $_SESSION['user_id']; ?>">
    <div class="app-container">
        <!-- sidebar navigation -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <!-- simple logo instead of fancy icon -->
                    <span>[📦] Inventory</span>
                </div>
                <div class="admin-profile">
                    <div class="user-info">
                        <div class="user-avatar">
                            <!-- basic user icon -->
                            <span>👤</span>
                        </div>
                        <div class="user-details">
                            <span class="user-name"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                            <span class="user-role"><?php echo $is_admin ? 'Administrator' : 'User'; ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <nav class="sidebar-nav">
                <ul>
                    <li>
                        <a href="/inventorysystem/dashboard.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                            <i class="fas fa-home"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <?php if ($is_admin): ?>
                    <li>
                        <a href="/inventorysystem/admin/manage_users.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'manage_users.php' ? 'active' : ''; ?>">
                            <i class="fas fa-users"></i>
                            <span>Users</span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>

            <div class="sidebar-footer">
                <a href="/inventorysystem/logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="content-header">
                <div class="header-search">
                    <div class="search-box">
                        <input type="text" placeholder="Search...">
                        <i class="fas fa-search"></i>
                    </div>
                </div>
            </header>
            <div class="content-wrapper">
