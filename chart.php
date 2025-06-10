<?php


/**
 * Charts & Analytics Page
 * 
 * Displays interactive charts and data visualizations for:
 * - Inventory status distribution
 * - Monthly sales trends
 * - Top-selling products
 * - Order status breakdown
 */

require_once 'config/db.php';
require_once 'config/auth.php';

// Check user authentication and get role
$current_user_role = getCurrentUserRole($conn);

/**
 * Get inventory statistics grouped by stock levels
 * 
 * Categorizes products into stock status groups:
 * - Out of Stock: quantity = 0
 * - Critical: quantity <= alert_quantity/2  
 * - Low: quantity <= alert_quantity
 * - Normal: quantity > alert_quantity
 * 
 * @param mysqli $conn Database connection
 * @return array Associative array with stock status counts
 */
function getInventoryStatsForChart($conn) {
    // SQL query to categorize products by stock levels using CASE statements
    $sql = "SELECT 
        CASE 
            WHEN quantity = 0 THEN 'Out of Stock'
            WHEN quantity <= alert_quantity/2 THEN 'Critical'
            WHEN quantity <= alert_quantity THEN 'Low'
            ELSE 'Normal'
        END as stock_status,
        COUNT(*) as count
    FROM products
    GROUP BY stock_status
    ORDER BY FIELD(stock_status, 'Out of Stock', 'Critical', 'Low', 'Normal')";
      $result = $conn->query($sql);
    $data = []; // Initialize array to store results
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $data[$row['stock_status']] = (int)$row['count']; // Convert to integer
        }
    }
    
    return $data; // Return the processed data
}

/**
 * Get sales data for line charts
 * Retrieves monthly sales data to show revenue trends over time
 * 
 * @param mysqli $conn Database connection
 * @return array Array containing labels and data for chart
 */
function getSalesDataForChart($conn) {
    // Get sales data for the past 6 months
    $sql = "SELECT 
        DATE_FORMAT(o.created_at, '%Y-%m') as month,
        SUM(o.total_amount) as revenue
    FROM orders o
    WHERE o.status = 'completed'
    AND o.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY month
    ORDER BY month ASC"; // Order from oldest to newest
    
    $result = $conn->query($sql);
    $labels = []; // Array for month names displayed on chart
    $data = [];   // Array for revenue amounts
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $month = date('M', strtotime($row['month'] . '-01')); // Format as "Jan", "Feb", etc.
            $labels[] = $month;
            $data[] = floatval($row['revenue']); // Ensure numeric value
        }    } else {
        // Use sample data if no real sales data exists
        $labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'];
        $data = [15000, 21000, 18000, 24000, 27000, 25000]; // Sample revenue figures
    }
    
    return ['labels' => $labels, 'data' => $data]; // Return both arrays
}

/**
 * Get top selling products for bar chart
 * Displays the products with highest sales quantities
 * 
 * @param mysqli $conn Database connection
 * @param int $limit Maximum number of products to return (default: 5)
 * @return array Array containing product names and quantities
 */
function getTopSellingProducts($conn, $limit = 5) {
    $sql = "SELECT 
        p.name as product_name,
        COALESCE(SUM(oi.quantity), 0) as total_sold
    FROM products p
    LEFT JOIN order_items oi ON p.id = oi.product_id
    LEFT JOIN orders o ON oi.order_id = o.id AND o.status = 'completed'
    GROUP BY p.id
    ORDER BY total_sold DESC
    LIMIT ?"; // Limit to specified number of top products
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return []; // Return empty array if query preparation fails
    }
    
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $product_names = [];     // Array for product names
    $product_quantities = []; // Array for quantities sold
      if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $product_names[] = $row['product_name'];
            $product_quantities[] = (int)$row['total_sold'];
        }
    } else {
        // Use sample data if no real sales exist yet
        $product_names = ['Laptop - Dell XPS 13', 'Smartphone - iPhone 14', 'Wireless Mouse', 'Bluetooth Headphones', 'USB-C Cable'];
        $product_quantities = [45, 38, 30, 25, 20];
    }
    
    return ['names' => $product_names, 'quantities' => $product_quantities];
}

/**
 * Get order status chart data
 * Shows distribution of orders by their current status
 * 
 * @param mysqli $conn Database connection
 * @return array Array containing status names and counts
 */
function getOrderStatusData($conn) {
    $sql = "SELECT 
        status,
        COUNT(*) as count
    FROM orders
    GROUP BY status
    ORDER BY count DESC"; // Most common status first
    
    $result = $conn->query($sql);
    $statuses = []; // Order status names
    $counts = [];   // Count for each status
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $statuses[] = ucfirst($row['status']); // Capitalize first letter
            $counts[] = (int)$row['count'];
        }
    } else {
        // Sample data if no orders exist
        $statuses = ['Completed', 'Pending', 'Processing', 'Cancelled'];
        $counts = [120, 45, 30, 15];
    }
    
    return ['statuses' => $statuses, 'counts' => $counts];
}

// Retrieve all chart data from database functions
$inventory_stats = getInventoryStatsForChart($conn);
$sales_data = getSalesDataForChart($conn);
$top_products = getTopSellingProducts($conn);
$order_statuses = getOrderStatusData($conn);

$page_title = "Charts & Analytics"; // Set page title for HTML head
$current_page = 'chart'; // Set current page for navigation highlighting
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Inventory Management System</title>
    
    <!-- External stylesheets -->
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/sidebar.css">
    
    <!-- Chart.js library for interactive charts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        /* Chart page styling */
        .chart-container {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .chart-info {
            background: white;
            border: 1px solid #ddd;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .chart-info h2 {
            margin-top: 0;
            color: #333;
            font-size: 24px;
            margin-bottom: 10px;
            border-bottom: 1px solid #ccc;
            padding-bottom: 5px;
        }
        
        .chart-info-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .info-card {
            background: #f5f5f5;
            padding: 15px;
            border-left: 4px solid #0066cc;
            flex: 1;
            min-width: 300px;
        }
        
        .info-card h3 {
            color: #333;
            font-size: 18px;
            margin-top: 0;
            margin-bottom: 10px;
        }
        
        .info-card p {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
        }
        
        .info-card ul {
            margin: 0;
            padding-left: 20px;
            color: #555;
            font-size: 14px;
        }
        
        .info-card ul li {
            margin-bottom: 5px;
        }        
        /* Color variations for different info cards */
        .info-card:nth-child(2) {
            border-left-color: #00cc66;
        }
        
        .info-card:nth-child(3) {
            border-left-color: #ffaa00;
        }
        
        .info-card:nth-child(4) {
            border-left-color: #9966cc;
        }
        
        .chart-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .chart-card {
            background: white;
            border: 1px solid #ddd;
            padding: 15px;
            flex: 1;
            min-width: 500px;
        }
        
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ddd;
        }
        
        .chart-header h2 {
            margin: 0;
            font-size: 20px;
            color: #333;
        }
        
        .chart-header .icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            color: white;
            font-size: 20px;
        }        
        /* Chart icon color schemes */
        .icon.inventory {
            background-color: #0066cc;
        }
        
        .icon.sales {
            background-color: #00cc66;
        }
        
        .icon.products {
            background-color: #ffaa00;
        }
        
        .icon.orders {
            background-color: #9966cc;
        }
        
        .chart-content {
            height: 300px;
            position: relative;
        }        
        /* Mobile responsive design */
        @media (max-width: 768px) {
            .chart-grid {
                flex-direction: column;
            }
            
            .chart-card {
                min-width: auto;
            }
            
            .chart-info-grid {
                flex-direction: column;
            }
            
            .info-card {
                min-width: auto;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Include Sidebar -->
        <?php require_once 'templates/sidebar.php'; ?>        <!-- Main Content -->
        <main class="main-content">
            <div class="chart-container">
                <header class="dashboard-header">
                    <h1><?php echo $page_title; ?></h1>
                    <div class="header-actions">
                        <a href="#chart-info" class="btn btn-secondary" style="margin-right: 10px;">
                            ℹ️ About Charts
                        </a>
                        <button class="btn btn-primary" onclick="window.print()">
                            🖨️ Print Charts
                        </button>
                    </div>
                </header>
                
                <!-- Charts Grid -->
                <div class="chart-grid"><!-- Inventory Status Chart -->
                    <div class="chart-card">
                        <div class="chart-header">
                            <h2>Inventory Status</h2>
                            <div class="icon inventory">
                                📦
                            </div>
                        </div>
                        <div class="chart-content">
                            <canvas id="inventoryChart"></canvas>
                        </div>
                    </div>

                    <!-- Monthly Sales Chart -->
                    <div class="chart-card">
                        <div class="chart-header">
                            <h2>Monthly Sales Revenue</h2>
                            <div class="icon sales">
                                📈
                            </div>
                        </div>
                        <div class="chart-content">
                            <canvas id="salesChart"></canvas>
                        </div>
                    </div>

                    <!-- Top Selling Products Chart -->
                    <div class="chart-card">
                        <div class="chart-header">
                            <h2>Top Selling Products</h2>
                            <div class="icon products">
                                ⭐
                            </div>
                        </div>
                        <div class="chart-content">
                            <canvas id="productsChart"></canvas>
                        </div>
                    </div>

                    <!-- Order Status Chart -->
                    <div class="chart-card">
                        <div class="chart-header">
                            <h2>Order Status Distribution</h2>
                            <div class="icon orders">
                                🛒
                            </div>
                        </div>
                        <div class="chart-content">
                            <canvas id="orderStatusChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>    <script>
        // Chart.js initialization - Creates all interactive charts on the page
        document.addEventListener('DOMContentLoaded', function() {
            // Inventory Status Chart - Displays current stock levels distribution
            const inventoryCtx = document.getElementById('inventoryChart').getContext('2d');
            new Chart(inventoryCtx, {
                type: 'pie', // Pie chart effectively shows proportional data
                data: {
                    labels: <?php echo json_encode(array_keys($inventory_stats)); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_values($inventory_stats)); ?>,
                        backgroundColor: [
                            '#ef4444', // Out of Stock - Red (critical)
                            '#f97316', // Critical - Orange (warning)
                            '#eab308', // Low - Yellow (caution)
                            '#22c55e'  // Normal - Green (healthy)
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true, // Automatically resizes with container
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right' // Position legend on the right side
                        }
                    }
                }
            });

            // Monthly Sales Chart - Revenue trend visualization
            const salesCtx = document.getElementById('salesChart').getContext('2d');
            new Chart(salesCtx, {
                type: 'line', // Line chart optimal for trend analysis
                data: {
                    labels: <?php echo json_encode($sales_data['labels']); ?>,
                    datasets: [{
                        label: 'Revenue ($)',
                        data: <?php echo json_encode($sales_data['data']); ?>,
                        backgroundColor: 'rgba(16, 185, 129, 0.2)', // Light green fill
                        borderColor: 'rgba(16, 185, 129, 1)', // Darker green line
                        borderWidth: 2,
                        tension: 0.4, // Smooth curve rendering
                        fill: true // Area fill under the line
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true, // Start y-axis at zero
                            ticks: {
                                callback: function(value) {
                                    return '$' + value; // Currency formatting
                                }
                            }
                        }
                    }
                }
            });

            // Top Selling Products Chart - Horizontal bar visualization
            const productsCtx = document.getElementById('productsChart').getContext('2d');
            new Chart(productsCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($top_products['names']); ?>,
                    datasets: [{
                        label: 'Units Sold',
                        data: <?php echo json_encode($top_products['quantities']); ?>,
                        backgroundColor: 'rgba(245, 158, 11, 0.8)', // Orange color scheme
                        borderWidth: 0,
                        borderRadius: 4 // Rounded corner styling
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y', // Horizontal bar orientation
                    scales: {
                        x: {
                            beginAtZero: true
                        }
                    }
                }
            });

            // Order Status Chart - Doughnut chart for status distribution
            const orderStatusCtx = document.getElementById('orderStatusChart').getContext('2d');
            new Chart(orderStatusCtx, {
                type: 'doughnut', // Doughnut provides modern appearance
                data: {
                    labels: <?php echo json_encode($order_statuses['statuses']); ?>,
                    datasets: [{
                        data: <?php echo json_encode($order_statuses['counts']); ?>,
                        backgroundColor: [
                            '#22c55e', // Completed - Green (success)
                            '#f59e0b', // Pending - Yellow (waiting)
                            '#3b82f6', // Processing - Blue (in progress)
                            '#6b7280'  // Cancelled - Gray (inactive)
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right'
                        }
                    }
                }
            });
        });
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
    