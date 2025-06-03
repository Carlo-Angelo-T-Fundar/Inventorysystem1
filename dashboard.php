<?php
// dashboard.php - main page after login
require_once 'config/db.php';
require_once 'config/auth.php';

// Add sample data if table is empty
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

// Function to get inventory data for dashboard
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

// Get the inventory data
$inventory_items = getInventoryData($conn);

// Get current user info
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

// Get monthly sales data for chart
function getMonthlySalesData($conn) {
    $sql = "SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as units_sold,
        COUNT(*) as total_transactions,
        COALESCE(SUM(CAST(total_amount AS DECIMAL(10,2))), 0) as revenue
    FROM orders 
    WHERE status = 'completed' 
    AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month ASC";
    
    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

$monthly_sales = getMonthlySalesData($conn);

$page_title = "Dashboard";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Inventory System</title>
    
    <!-- CDN Dependencies -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Local Styles -->
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/sidebar.css">
    
    <style>
        /* Dashboard Styles */
        .dashboard-container {
            display: flex;
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
            background: #f8f9fa;
        }
        
        .main-content {
            flex: 1;
            padding: 24px;
            max-width: calc(100% - 250px);
            overflow-x: auto;
        }
        
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }
        
        .dashboard-header h1 {
            font-family: 'Inter', sans-serif;
            color: #1f2937;
            margin: 0;
            font-size: 32px;
            font-weight: 600;
        }
        
        .header-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .search-bar {
            display: flex;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .search-input {
            padding: 10px 16px;
            border: none;
            font-size: 14px;
            width: 200px;
            font-family: 'Inter', sans-serif;
            outline: none;
        }
        
        .search-btn {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 10px 16px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.2s;
        }
        
        .search-btn:hover {
            background: #2563eb;
        }
          /* Statistics Cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 32px;
        }
        
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            border: 1px solid #e5e7eb;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .stat-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 0;
        }
        
        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            color: white;
        }
        
        .stat-icon.revenue { background: #10b981; }
        .stat-icon.orders { background: #3b82f6; }
        .stat-icon.pending { background: #f59e0b; }
        .stat-icon.completed { background: #06b6d4; }
        
        .stat-details h3 {
            margin: 0;
            font-size: 14px;
            color: #6b7280;
            font-weight: 500;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: 600;
            color: #1f2937;
            margin: 4px 0 0 0;
        }        /* Chart Section */
        .charts-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 32px;
        }
        
        .chart-section {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            border: 1px solid #e5e7eb;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
        }
        
        /* Data Tables */
        .data-tables {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 24px;
        }
        
        .table-section {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid #e5e7eb;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 24px;
            border-bottom: 1px solid #e5e7eb;
            background: #f9fafb;
        }
        
        .section-header h2 {
            margin: 0;
            font-size: 18px;
            color: #1f2937;
            font-weight: 600;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            background: #3b82f6;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            transition: background-color 0.2s;
        }
        
        .btn:hover {
            background: #2563eb;
            color: white;
        }
        
        /* Table Styles */
        .table-container {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        table th, table td {
            padding: 12px 24px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
            font-size: 14px;
        }
        
        table th {
            background: #f9fafb;
            font-weight: 600;
            color: #374151;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        table td {
            color: #6b7280;
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-badge.completed {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-badge.pending {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-badge.low-stock {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .no-data {
            text-align: center;
            color: #9ca3af;
            font-style: italic;
            padding: 40px 24px;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .main-content {
                max-width: 100%;
                padding: 16px;
            }
            
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }
            
            .dashboard-header h1 {
                font-size: 24px;
            }
            
            .stats-container {
                grid-template-columns: 1fr;
            }
              .data-tables {
                grid-template-columns: 1fr;
            }
            
            .charts-grid {
                grid-template-columns: 1fr;
            }
            
            .search-input {
                width: 150px;
            }
            
            table th, table td {
                padding: 8px 12px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Include Sidebar -->
        <?php 
        $current_page = 'dashboard';
        require_once 'templates/sidebar.php'; 
        ?>

        <!-- Main Content -->
        <main class="main-content">
            <header class="dashboard-header">
                <h1>Dashboard</h1>
                <div class="header-actions">
                    <div class="search-bar">
                        <input type="text" placeholder="Search..." class="search-input">
                        <button type="button" class="search-btn">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
            </header>            <!-- Statistics Cards -->
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon revenue">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <div class="stat-details">
                            <h3>Revenue</h3>
                            <div class="stat-value">$<?php echo number_format($order_stats['total_revenue'], 2); ?></div>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon orders">
                            <i class="fas fa-undo"></i>
                        </div>
                        <div class="stat-details">
                            <h3>Sales Return</h3>
                            <div class="stat-value"><?php echo number_format($order_stats['pending_orders']); ?></div>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon pending">
                            <i class="fas fa-shopping-bag"></i>
                        </div>
                        <div class="stat-details">
                            <h3>Orders</h3>
                            <div class="stat-value"><?php echo number_format($order_stats['total_orders']); ?></div>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon completed">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="stat-details">
                            <h3>Income</h3>
                            <div class="stat-value">$<?php echo number_format($order_stats['total_revenue'] * 0.85, 2); ?></div>
                        </div>
                    </div>
                </div>            </div><!-- Chart Section -->            
            <!-- Sales Analytics Charts -->
            <div class="charts-grid">
                <div class="chart-section">
                    <div class="section-header" style="border-bottom: 1px solid #e5e7eb; margin: -20px -20px 20px -20px; padding: 20px;">
                        <h2><i class="fas fa-chart-bar"></i> Sales Analytics</h2>
                    </div>
                    <div class="chart-container">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>
                
                <div class="chart-section">
                    <div class="section-header" style="border-bottom: 1px solid #e5e7eb; margin: -20px -20px 20px -20px; padding: 20px;">
                        <h2><i class="fas fa-dollar-sign"></i> Income Analytics</h2>
                    </div>
                    <div class="chart-container">
                        <canvas id="incomeChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Data Tables -->
            <div class="data-tables">
                <!-- Recent Sales Table -->
                <div class="table-section">
                    <div class="section-header">
                        <h2><i class="fas fa-receipt"></i> Recent Sales</h2>
                        <a href="sales.php" class="btn">
                            <i class="fas fa-arrow-right"></i>
                            View All Sales
                        </a>
                    </div>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($recent_sales)): ?>
                                    <?php foreach ($recent_sales as $sale): ?>
                                    <tr>
                                        <td>#<?php echo $sale['id']; ?></td>
                                        <td>$<?php echo number_format($sale['total_amount'], 2); ?></td>
                                        <td>
                                            <span class="status-badge <?php echo strtolower($sale['status']); ?>">
                                                <?php echo ucfirst($sale['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($sale['created_at'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="no-data">No sales found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Low Stock Items Table -->
                <div class="table-section">
                    <div class="section-header">
                        <h2><i class="fas fa-exclamation-triangle"></i> Low Stock Items</h2>
                        <a href="inventory.php" class="btn">
                            <i class="fas fa-arrow-right"></i>
                            View All Inventory
                        </a>
                    </div>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Product Name</th>
                                    <th>Quantity</th>
                                    <th>Alert Level</th>
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
                                            <i class="fas fa-exclamation-circle"></i>
                                            Low Stock
                                        </span>
                                    </td>
                                </tr>
                                <?php 
                                    endwhile;
                                else:
                                ?>
                                <tr>
                                    <td colspan="4" class="no-data">All items are well stocked</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>    <script>
        // Chart.js Implementation
        const salesCtx = document.getElementById('salesChart').getContext('2d');
        const incomeCtx = document.getElementById('incomeChart').getContext('2d');
        
        // Prepare chart data
        const monthlyData = <?php echo json_encode($monthly_sales); ?>;
        const labels = [];
        const unitsSold = [];
        const revenue = [];
        
        // If no data, show sample data
        if (monthlyData.length === 0) {
            const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'];
            const sampleUnits = [120, 150, 180, 140, 200, 160];
            const sampleRevenue = [12500, 15000, 18500, 14200, 20800, 16200];
            
            labels.push(...monthNames);
            unitsSold.push(...sampleUnits);
            revenue.push(...sampleRevenue);
        } else {
            monthlyData.forEach(item => {
                const date = new Date(item.month + '-01');
                labels.push(date.toLocaleDateString('en-US', { month: 'short' }));
                unitsSold.push(parseInt(item.units_sold) || 0);
                revenue.push(parseFloat(item.revenue) || 0);
            });
        }

        // Sales Chart (Blue bars only)
        const salesChart = new Chart(salesCtx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Units Sold',
                    data: unitsSold,
                    backgroundColor: '#4F81BD',
                    borderColor: '#4F81BD',
                    borderWidth: 1,
                    borderRadius: 4,
                    borderSkipped: false,
                    barThickness: 40,
                    maxBarThickness: 50
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        align: 'start',
                        labels: {
                            usePointStyle: true,
                            padding: 20,
                            font: {
                                family: 'Inter',
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#ffffff',
                        bodyColor: '#ffffff',
                        borderColor: '#4F81BD',
                        borderWidth: 1
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: true,
                            color: '#E5E7EB',
                            lineWidth: 1
                        },
                        ticks: {
                            color: '#6B7280',
                            font: {
                                family: 'Inter',
                                size: 12
                            }
                        }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Units Sold',
                            color: '#374151',
                            font: {
                                family: 'Inter',
                                size: 12,
                                weight: 'bold'
                            }
                        },
                        grid: {
                            display: true,
                            color: '#E5E7EB',
                            lineWidth: 1
                        },
                        ticks: {
                            color: '#6B7280',
                            font: {
                                family: 'Inter',
                                size: 11
                            }
                        }
                    }
                },
                interaction: {
                    mode: 'index',
                    intersect: false
                }
            }
        });

        // Income Chart (Orange bars)
        const incomeChart = new Chart(incomeCtx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Revenue',
                    data: revenue,
                    backgroundColor: '#F79646',
                    borderColor: '#F79646',
                    borderWidth: 1,
                    borderRadius: 4,
                    borderSkipped: false,
                    barThickness: 40,
                    maxBarThickness: 50
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        align: 'start',
                        labels: {
                            usePointStyle: true,
                            padding: 20,
                            font: {
                                family: 'Inter',
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#ffffff',
                        bodyColor: '#ffffff',
                        borderColor: '#F79646',
                        borderWidth: 1,
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': $' + context.raw.toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: true,
                            color: '#E5E7EB',
                            lineWidth: 1
                        },
                        ticks: {
                            color: '#6B7280',
                            font: {
                                family: 'Inter',
                                size: 12
                            }
                        }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Revenue ($)',
                            color: '#374151',
                            font: {
                                family: 'Inter',
                                size: 12,
                                weight: 'bold'
                            }
                        },
                        grid: {
                            display: true,
                            color: '#E5E7EB',
                            lineWidth: 1
                        },
                        ticks: {
                            color: '#6B7280',
                            font: {
                                family: 'Inter',
                                size: 11
                            },
                            callback: function(value) {
                                return '$' + value.toLocaleString();
                            }
                        }
                    }
                },
                interaction: {
                    mode: 'index',
                    intersect: false
                }
            }
        });

        // Search functionality
        document.querySelector('.search-input').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                performSearch();
            }
        });

        document.querySelector('.search-btn').addEventListener('click', performSearch);

        function performSearch() {
            const searchTerm = document.querySelector('.search-input').value.trim();
            if (searchTerm) {
                // Redirect to inventory page with search parameter
                window.location.href = `inventory.php?search=${encodeURIComponent(searchTerm)}`;
            }
        }
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