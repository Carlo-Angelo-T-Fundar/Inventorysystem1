<?php
// chart.php - this shows cool charts and graphs for the inventory system
// learned about Chart.js library in web dev class - pretty neat stuff!
require_once 'config/db.php';
require_once 'config/auth.php';

// all users can see charts - makes sense since it's just visual data
$current_user_role = getCurrentUserRole($conn);

// function to get inventory data for making pie charts
// this groups products by how much stock they have
function getInventoryStatsForChart($conn) {
    // SQL query to categorize products by stock levels
    // learned about CASE statements - they're like if/else but in SQL
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
    $data = []; // empty array to store results
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $data[$row['stock_status']] = (int)$row['count']; // convert to number
        }
    }
    
    return $data; // send back the data
}

// function to get sales data for line charts
// this gets monthly sales to show trends over time
function getSalesDataForChart($conn) {
    // get sales data for the past 6 months - prof said this is a good time range
    $sql = "SELECT 
        DATE_FORMAT(o.created_at, '%Y-%m') as month,
        SUM(o.total_amount) as revenue
    FROM orders o
    WHERE o.status = 'completed'
    AND o.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY month
    ORDER BY month ASC"; // oldest first
    
    $result = $conn->query($sql);
    $labels = []; // month names for the chart
    $data = [];   // revenue amounts
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $month = date('M Y', strtotime($row['month'] . '-01')); // format like "Jan 2024"
            $labels[] = $month;
            $data[] = floatval($row['revenue']); // make sure it's a number
        }
    } else {
        // if no real data, use fake data so chart doesn't break
        $labels = ['Jan 2024', 'Feb 2024', 'Mar 2024', 'Apr 2024', 'May 2024', 'Jun 2024'];
        $data = [15000, 21000, 18000, 24000, 27000, 25000]; // made up numbers
    }
    
    return ['labels' => $labels, 'data' => $data]; // return both arrays
}

// function to get top selling products for bar chart
// this shows which products sell the most
function getTopSellingProducts($conn, $limit = 5) {
    $sql = "SELECT 
        p.name as product_name,
        COALESCE(SUM(oi.quantity), 0) as total_sold
    FROM products p
    LEFT JOIN order_items oi ON p.id = oi.product_id
    LEFT JOIN orders o ON oi.order_id = o.id AND o.status = 'completed'
    GROUP BY p.id
    ORDER BY total_sold DESC
    LIMIT ?"; // only get top 5
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return []; // return empty if query fails
    }
    
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $product_names = [];     // array for product names
    $product_quantities = []; // array for quantities sold
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $product_names[] = $row['product_name'];
            $product_quantities[] = (int)$row['total_sold'];
        }
    } else {
        // fake data if no real sales exist yet
        $product_names = ['Laptop - Dell XPS 13', 'Smartphone - iPhone 14', 'Wireless Mouse', 'Bluetooth Headphones', 'USB-C Cable'];
        $product_quantities = [45, 38, 30, 25, 20];
    }
    
    return ['names' => $product_names, 'quantities' => $product_quantities];
}

// function to get order status chart data
// shows how many orders are completed, pending, etc.
function getOrderStatusData($conn) {
    $sql = "SELECT 
        status,
        COUNT(*) as count
    FROM orders
    GROUP BY status
    ORDER BY count DESC"; // most common status first
    
    $result = $conn->query($sql);
    $statuses = []; // order status names
    $counts = [];   // how many of each status
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $statuses[] = ucfirst($row['status']); // capitalize first letter
            $counts[] = (int)$row['count'];
        }
    } else {
        // sample data if no orders exist
        $statuses = ['Completed', 'Pending', 'Processing', 'Cancelled'];
        $counts = [120, 45, 30, 15];
    }
    
    return ['statuses' => $statuses, 'counts' => $counts];
}

// actually get all the data we need for the charts
$inventory_stats = getInventoryStatsForChart($conn);
$sales_data = getSalesDataForChart($conn);
$top_products = getTopSellingProducts($conn);
$order_statuses = getOrderStatusData($conn);

$page_title = "Charts & Analytics"; // page title
$current_page = 'chart'; // for navigation highlighting
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Inventory Management System</title>
    <!-- using basic fonts and simple icons instead of fancy external stuff -->    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dashboard.css">    <link rel="stylesheet" href="css/sidebar.css">
    <!-- Chart.js library - this is what makes the charts work -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>    <style>
        /* basic styling for charts - learned about CSS grid in web design class */
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
        
        /* different colors for different cards - just simple stuff */
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
        
        /* basic colors for the chart icons */
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
        
        /* basic mobile support - learned this in responsive design */
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
        <?php require_once 'templates/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <header class="dashboard-header">
                <h1><?php echo $page_title; ?></h1>                <div class="header-actions">
                    <a href="#chart-info" class="btn btn-secondary" style="margin-right: 10px;">
                        ℹ️ About Charts
                    </a>
                    <button class="btn btn-primary" onclick="window.print()">
                        🖨️ Print Charts
                    </button>
                </div>
            </header>                     
                <!-- Charts Grid -->
                <div class="chart-grid">                    <!-- Inventory Status Chart -->
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
        // Chart.js initialization - this creates all the charts on the page
        // learned about Chart.js in my web development class - it's pretty cool!
        document.addEventListener('DOMContentLoaded', function() {
            // Inventory Status Chart - shows how much stock we have
            const inventoryCtx = document.getElementById('inventoryChart').getContext('2d');
            new Chart(inventoryCtx, {
                type: 'pie', // pie chart because it shows parts of a whole
                data: {
                    labels: <?php echo json_encode(array_keys($inventory_stats)); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_values($inventory_stats)); ?>,
                        backgroundColor: [
                            '#ef4444', // Out of Stock - Red (bad!)
                            '#f97316', // Critical - Orange (warning!)
                            '#eab308', // Low - Yellow (caution)
                            '#22c55e'  // Normal - Green (good!)
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true, // makes it resize with the container
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right' // put the legend on the right side
                        }
                    }
                }
            });

            // Monthly Sales Chart - shows revenue over time
            const salesCtx = document.getElementById('salesChart').getContext('2d');
            new Chart(salesCtx, {
                type: 'line', // line chart is good for showing trends
                data: {
                    labels: <?php echo json_encode($sales_data['labels']); ?>,
                    datasets: [{
                        label: 'Revenue ($)',
                        data: <?php echo json_encode($sales_data['data']); ?>,
                        backgroundColor: 'rgba(16, 185, 129, 0.2)', // light green fill
                        borderColor: 'rgba(16, 185, 129, 1)', // darker green line
                        borderWidth: 2,
                        tension: 0.4, // makes the line curved instead of sharp angles
                        fill: true // fills the area under the line
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true, // always start y-axis at 0
                            ticks: {
                                callback: function(value) {
                                    return '$' + value; // add dollar sign to numbers
                                }
                            }
                        }
                    }
                }
            });

            // Top Selling Products Chart - horizontal bar chart
            const productsCtx = document.getElementById('productsChart').getContext('2d');
            new Chart(productsCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($top_products['names']); ?>,
                    datasets: [{
                        label: 'Units Sold',
                        data: <?php echo json_encode($top_products['quantities']); ?>,
                        backgroundColor: 'rgba(245, 158, 11, 0.8)', // orange color
                        borderWidth: 0,
                        borderRadius: 4 // makes the bars have rounded corners
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y', // this makes it horizontal instead of vertical
                    scales: {
                        x: {
                            beginAtZero: true
                        }
                    }
                }
            });

            // Order Status Chart - doughnut chart (like pie but with hole in middle)
            const orderStatusCtx = document.getElementById('orderStatusChart').getContext('2d');
            new Chart(orderStatusCtx, {
                type: 'doughnut', // doughnut looks cooler than regular pie
                data: {
                    labels: <?php echo json_encode($order_statuses['statuses']); ?>,
                    datasets: [{
                        data: <?php echo json_encode($order_statuses['counts']); ?>,
                        backgroundColor: [
                            '#22c55e', // Completed - Green (successful!)
                            '#f59e0b', // Pending - Yellow (waiting)
                            '#3b82f6', // Processing - Blue (working on it)
                            '#6b7280'  // Cancelled - Gray (not good)
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
