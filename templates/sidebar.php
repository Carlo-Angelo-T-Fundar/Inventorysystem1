<?php
// sidebar.php - navigation menu for the site
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
<aside class="sidebar">
    <div class="admin-header">
        <div class="profile-section">
            <div class="admin-avatar">
                <!-- simple user icon instead of fancy font awesome -->
                <span>👤</span>
            </div>
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
                    </span>
                </div>
                <div class="online-status">
                    <span style="color: green;">●</span> Online
                </div>
            </div>
        </div>
    </div>    <nav class="sidebar-nav">
        <!-- navigation links - show different ones based on user role -->
        <a href="dashboard.php" class="nav-link <?php echo $current_page === 'dashboard' ? 'active' : ''; ?>">Dashboard</a>
        
        <?php if (in_array($user_role, ['admin', 'store_clerk', 'supplier', 'cashier'])): ?>
        <a href="inventory.php" class="nav-link <?php echo $current_page === 'inventory' ? 'active' : ''; ?>">Inventory</a>
        <a href="inventory_transactions.php" class="nav-link <?php echo $current_page === 'inventory_transactions' ? 'active' : ''; ?>">Inventory Transactions</a>
        <?php endif; ?>
        
        <?php if (in_array($user_role, ['admin', 'store_clerk'])): ?>
        <a href="order.php" class="nav-link <?php echo $current_page === 'order' ? 'active' : ''; ?>">Order</a>
        <?php endif; ?>
        
        <?php if (in_array($user_role, ['admin', 'cashier'])): ?>
        <a href="sales.php" class="nav-link <?php echo $current_page === 'sales' ? 'active' : ''; ?>">Sales</a>
        <?php endif; ?>
        
        <a href="chart.php" class="nav-link <?php echo $current_page === 'chart' ? 'active' : ''; ?>">Chart</a>
        
        <?php if ($user_role === 'admin'): ?>
        <a href="users_crud.php" class="nav-link <?php echo $current_page === 'users_crud' ? 'active' : ''; ?>">User Management</a>
        <a href="user_activity_logs.php" class="nav-link <?php echo $current_page === 'user_activity_logs' ? 'active' : ''; ?>">Activity Logs</a>
        <?php endif; ?>
    </nav>
    
    <div class="sidebar-footer">
        <!-- bottom navigation stuff -->
        <a href="profile.php" class="<?php echo $current_page === 'profile' ? 'active' : ''; ?>">
            <span>👤</span> Profile
        </a>
        <a href="change-password.php" class="<?php echo $current_page === 'change-password' ? 'active' : ''; ?>">
            <span>🔑</span> Change Password
        </a>
        <a href="logout.php" class="logout">
            <span>🚪</span> Logout
        </a>
    </div>
</aside>
