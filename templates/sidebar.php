<?php
// sidebar.php - navigation menu for the site (CSS-only version)
// figure out what page we're on
$current_page = basename($_SERVER['PHP_SELF'], '.php');

// get user role so we know what to show them
$user_role = getCurrentUserRole($conn);
$role_labels = [
    'admin' => 'ADMIN',
    'supplier' => 'SUPPLIER', 
    'store_clerk' => 'STORE CLERK',
    'cashier' => 'CASHIER'
];
$role_display = $role_labels[$user_role] ?? strtoupper($user_role);
?>

<!-- CSS-only mobile sidebar toggle -->
<input type="checkbox" id="sidebar-toggle" style="display: none;">

<!-- Mobile menu toggle button (CSS-only) -->
<label for="sidebar-toggle" class="mobile-menu-toggle">
    <span></span>
    <span></span>
    <span></span>
</label>

<!-- Sidebar overlay for mobile (CSS-only) -->
<label for="sidebar-toggle" class="sidebar-overlay"></label>

<aside class="sidebar" id="sidebar" data-page="<?php echo $current_page; ?>">
    <div class="admin-header">        <div class="profile-section">            <!-- Profile link -->
            <a href="profile.php" class="admin-avatar" title="Profile Settings">
                <!-- simple user icon instead of fancy font awesome -->
                <span aria-hidden="true">👤</span>
                <span class="visually-hidden">Profile Settings</span>
            </a>
            <div class="profile-info">
                <div class="username">
                    <?php 
                    // get current user's name from database
                    if (isset($_SESSION['user_id'])) {
                        $user_stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
                        $user_stmt->bind_param("i", $_SESSION['user_id']);
                        $user_stmt->execute();
                        $user_result = $user_stmt->get_result();
                        $current_user = $user_result->fetch_assoc();
                        echo htmlspecialchars($current_user['username'] ?? 'User');
                    } else {
                        echo 'User';
                    }
                    ?>
                </div>
                <div class="user-role">
                    <span class="role-badge role-<?php echo strtolower(str_replace(' ', '_', $user_role)); ?>">
                        <?php echo $role_display; ?>
                    </span>                </div>
                <div class="online-status">
                    <span style="color: green;">●</span> Online
                </div>
            </div>
  
        </div>
    </div>    <nav class="sidebar-nav">
        <!-- navigation links - show different ones based on user role -->        <a href="dashboard.php" class="nav-link <?php echo $current_page === 'dashboard' ? 'active' : ''; ?>">
            <span class="nav-icon">📊</span>
            <span class="nav-text">Dashboard</span>
        </a>
        
        <?php if (in_array($user_role, ['admin', 'store_clerk', 'supplier', 'cashier'])): ?>
        <a href="inventory.php" class="nav-link <?php echo $current_page === 'inventory' ? 'active' : ''; ?>">
            <span class="nav-icon">📦</span>
            <span class="nav-text">Inventory</span>
        </a>
        <a href="inventory_transactions.php" class="nav-link <?php echo $current_page === 'inventory_transactions' ? 'active' : ''; ?>">
            <span class="nav-icon">📝</span>
            <span class="nav-text">Transactions</span>
        </a>
        <?php endif; ?>
          <?php if (in_array($user_role, ['admin', 'store_clerk'])): ?>
        <a href="order.php" class="nav-link <?php echo $current_page === 'order' ? 'active' : ''; ?>">
            <span class="nav-icon">🛒</span>
            <span class="nav-text">Orders</span>
        </a>
        <?php endif; ?>
        
        <?php if (in_array($user_role, ['admin', 'cashier'])): ?>
        <a href="sales.php" class="nav-link <?php echo $current_page === 'sales' ? 'active' : ''; ?>">
            <span class="nav-icon">💰</span>
            <span class="nav-text">Sales</span>
        </a>
        <?php endif; ?>
        
        <a href="chart.php" class="nav-link <?php echo $current_page === 'chart' ? 'active' : ''; ?>">
            <span class="nav-icon">📈</span>
            <span class="nav-text">Charts</span>
        </a>
          <?php if ($user_role === 'admin'): ?>
        <a href="users_crud.php" class="nav-link <?php echo $current_page === 'users_crud' ? 'active' : ''; ?>">
            <span class="nav-icon">👥</span>
            <span class="nav-text">User Management</span>
        </a>
        <a href="user_activity_logs.php" class="nav-link <?php echo $current_page === 'user_activity_logs' ? 'active' : ''; ?>">
            <span class="nav-icon">📋</span>
            <span class="nav-text">Activity Logs</span>
        </a>
        <?php endif; ?>
    </nav>
</aside>
