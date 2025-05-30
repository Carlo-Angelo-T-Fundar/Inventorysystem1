<?php
require_once 'config/db.php';
require_once 'config/auth.php';


// Insert sample data if the table is empty
$check_empty = $conn->query("SELECT COUNT(*) as count FROM products");
$row = $check_empty->fetch_assoc();
if ($row['count'] == 0) {
    $sample_data_sql = "INSERT INTO products (name, quantity, alert_quantity, price) VALUES 
        ('Product 1', 15, 10, 29.99),
        ('Product 2', 8, 10, 19.99),
        ('Product 3', 5, 10, 39.99),
        ('Product 4', 20, 10, 49.99),
        ('Product 5', 3, 10, 59.99)";
    
    if (!$conn->query($sample_data_sql)) {
        die("Error inserting sample data: " . $conn->error);
    }
}

// Function to get inventory data
function getInventoryData($conn) {
    $sql = "SELECT 
        id as product_id,
        name as product_name,
        quantity,
        alert_quantity,
        CASE 
            WHEN quantity <= alert_quantity THEN 'Low Stock'
            ELSE 'In Stock'
        END as status
    FROM products
    ORDER BY quantity ASC
    LIMIT 5";
    
    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

// Get inventory data
$inventory_items = getInventoryData($conn);

// Get user information
$stmt = $conn->prepare("SELECT id, username, created_at FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Check if user is admin
$is_admin = false;
$stmt = $conn->prepare("SELECT username FROM users WHERE id = ? AND username = 'admin'");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    $is_admin = true;
}

// Get order statistics
$order_stats = [
    'total_orders' => 0,
    'pending_orders' => 0,
    'completed_orders' => 0,
    'total_revenue' => 0
];

$sql = "SELECT 
    COUNT(*) as total_orders,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
    COALESCE(SUM(CASE WHEN status = 'completed' THEN CAST(total_amount AS DECIMAL(10,2)) ELSE 0 END), 0) as total_revenue
FROM orders";

$result = $conn->query($sql);
if ($result && $row = $result->fetch_assoc()) {
    $order_stats = $row;
}

// Get recent sales (completed orders only)
function getRecentSales($conn) {
    $sql = "SELECT o.*
            FROM orders o
            WHERE o.status = 'completed'
            ORDER BY o.created_at DESC
            LIMIT 5";
    return $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
}

$recent_sales = getRecentSales($conn);

$page_title = "Dashboard";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Inventory System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>    <div class="dashboard-container">
        <!-- Include Sidebar -->
        <?php 
        $current_page = 'dashboard';
        require_once 'templates/sidebar.php'; 
        ?>

        <!-- Main Content -->
        <main class="main-content">
            <header class="dashboard-header">
                <h1>Dashboard</h1>                <div class="header-actions">
                    <div class="search-bar">
                        <input type="text" placeholder="Search..." class="search-input">
                        <button type="button" class="search-btn"><i class="fas fa-search"></i></button>
                    </div>
                </div>
            </header>            <!-- Statistics Cards -->
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-icon revenue">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-details">
                        <span class="stat-title">Revenue</span>
                        <span class="stat-value">$<?php echo number_format($order_stats['total_revenue'], 2); ?></span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon orders">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-details">
                        <span class="stat-title">Total Orders</span>
                        <span class="stat-value"><?php echo $order_stats['total_orders']; ?></span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon pending">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-details">
                        <span class="stat-title">Pending Orders</span>
                        <span class="stat-value"><?php echo $order_stats['pending_orders']; ?></span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon completed">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-details">
                        <span class="stat-title">Completed Orders</span>
                        <span class="stat-value"><?php echo $order_stats['completed_orders']; ?></span>
                    </div>
                </div>
            </div>

            <!-- Chart Section -->
            <div class="chart-section">
                <canvas id="salesChart"></canvas>
            </div>

            <!-- Data Tables -->
            <div class="data-tables">                <!-- Recent Sales Table -->
                <div class="table-section">
                    <div class="section-header">
                        <h2>Recent Sales</h2>
                        <a href="sales.php" class="btn btn-primary">View All Sales</a>
                    </div>
                    <table>                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>                        <tbody>
                            <?php foreach ($recent_sales as $sale): ?>                            <tr>
                                <td>#<?php echo $sale['id']; ?></td>
                                <td>Customer Order</td>
                                <td>$<?php echo number_format($sale['total_amount'], 2); ?></td>
                                <td>
                                    <span class="status-badge <?php echo strtolower($sale['status']); ?>">
                                        <?php echo ucfirst($sale['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($sale['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($recent_sales)): ?>
                            <tr>
                                <td colspan="5" class="text-center">No sales found</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Low Stock Items Table -->
                <div class="table-section">
                    <div class="section-header">
                        <h2>Low Stock Items</h2>
                        <a href="inventory.php" class="btn btn-primary">View All Inventory</a>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Product Name</th>
                                <th>Quantity</th>
                                <th>Alert amt.</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $low_stock_query = "SELECT * FROM products WHERE quantity <= alert_quantity ORDER BY quantity ASC LIMIT 5";
                            $low_stock_result = $conn->query($low_stock_query);
                            if ($low_stock_result && $low_stock_result->num_rows > 0):
                                while ($item = $low_stock_result->fetch_assoc()):
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['name']); ?></td>
                                <td><?php echo htmlspecialchars($item['quantity']); ?></td>
                                <td><?php echo htmlspecialchars($item['alert_quantity']); ?></td>
                                <td>
                                    <span class="status-badge low-stock">
                                        Low Stock
                                    </span>
                                </td>
                            </tr>
                            <?php 
                                endwhile;
                            else:
                            ?>
                            <tr>
                                <td colspan="4" class="no-data">No low stock items</td>
                            </tr>
                            <?php endif; ?>                        </tbody>                    </table>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Initialize Chart
        const ctx = document.getElementById('salesChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Q1', 'Q2', 'Q3', 'Q4'],
                datasets: [{
                    label: 'Sales 2024',
                    data: [30000, 25000, 27000, 28000],
                    backgroundColor: '#FFD700',
                    barPercentage: 0.4
                }, {
                    label: 'Sales 2025',
                    data: [28000, 32000, 31000, 35000],
                    backgroundColor: '#4169E1',
                    barPercentage: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }        });
    </script>
    
    <!-- Auto-logout system -->
    <script src="css/auto-logout.js"></script>
    <script>
        // Mark body as logged in for auto-logout detection
        document.body.classList.add('logged-in');
        document.body.setAttribute('data-user-id', '<?php echo $_SESSION['user_id']; ?>');
    </script>
</body>
</html>
