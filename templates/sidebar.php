<?php
// Get the current page name
$current_page = basename($_SERVER['PHP_SELF'], '.php');

// Get user role for navigation
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
        <div class="admin-avatar">
            <i class="fas fa-user"></i>
        </div>
        <div>
            <h2><?php echo $role_display; ?></h2>
            <span class="online-status">Online</span>
        </div>
    </div>    
    <nav class="sidebar-nav">        
        <a href="dashboard.php" class="nav-link <?php echo $current_page === 'dashboard' ? 'active' : ''; ?>">Dashboard</a>
          <?php if (in_array($user_role, ['admin', 'store_clerk', 'supplier'])): ?>
        <a href="inventory.php" class="nav-link <?php echo $current_page === 'inventory' ? 'active' : ''; ?>">Inventory</a>
        <a href="inventory_transactions.php" class="nav-link <?php echo $current_page === 'inventory_transactions' ? 'active' : ''; ?>">Inventory Transactions</a>
        <?php endif; ?>
        
        <?php if (in_array($user_role, ['admin', 'cashier', 'store_clerk'])): ?>
        <a href="order.php" class="nav-link <?php echo $current_page === 'order' ? 'active' : ''; ?>">Order</a>
        <?php endif; ?>
          <?php if (in_array($user_role, ['admin', 'cashier'])): ?>
        <a href="sales.php" class="nav-link <?php echo $current_page === 'sales' ? 'active' : ''; ?>">Sales</a>
        <?php endif; ?>
          <a href="chart.php" class="nav-link <?php echo $current_page === 'chart' ? 'active' : ''; ?>">Chart</a>          <?php if ($user_role === 'admin'): ?>
        <a href="users_crud.php" class="nav-link <?php echo $current_page === 'users_crud' ? 'active' : ''; ?>">User Management</a>
        <a href="user_activity_logs.php" class="nav-link <?php echo $current_page === 'user_activity_logs' ? 'active' : ''; ?>">Activity Logs</a>
        <?php endif; ?>
    </nav>
    
    <div class="sidebar-footer">
        <a href="profile.php" class="<?php echo $current_page === 'profile' ? 'active' : ''; ?>">
            <i class="fas fa-user-circle"></i> Profile
        </a>
        <a href="change-password.php" class="<?php echo $current_page === 'change-password' ? 'active' : ''; ?>">
            <i class="fas fa-key"></i> Change Password
        </a>
        <a href="logout.php" class="logout">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</aside>
