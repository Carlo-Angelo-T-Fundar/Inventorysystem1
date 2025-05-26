<?php
// Sales API Endpoints
require_once 'config.php';

// Ensure we have the necessary tables
$create_sales_view = "CREATE OR REPLACE VIEW sales_summary AS
    SELECT 
        DATE(o.created_at) as sale_date,
        COUNT(o.id) as total_orders,
        SUM(o.total_amount) as total_revenue,
        AVG(o.total_amount) as average_order_value,
        SUM(oi.quantity) as total_items_sold
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE o.status = 'completed'
    GROUP BY DATE(o.created_at)
    ORDER BY sale_date DESC";

$conn->query($create_sales_view);

// Route handling
$action = isset($_GET['action']) ? $_GET['action'] : 'summary';

switch ($action) {
    case 'summary':
        handle_sales_summary($conn);
        break;
    case 'top_products':
        handle_top_products($conn);
        break;
    case 'add':
        handle_add_sale($conn);
        break;
    case 'by_date':
        handle_sales_by_date($conn);
        break;
    default:
        send_error('Invalid sales action', 404, 'INVALID_ENDPOINT');
}

// Get sales summary data
function handle_sales_summary($conn) {
    // Only allow GET for sales summary
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        send_error('Method not allowed', 405, 'METHOD_NOT_ALLOWED');
    }
    
    // Require authentication
    $user_id = require_auth($conn);
    
    // Only admins and cashiers can view sales data
    require_role($user_id, ['admin', 'cashier'], $conn);
    
    $sql = "SELECT 
        COUNT(id) as total_sales,
        COALESCE(SUM(total_amount), 0) as total_revenue,
        COALESCE(AVG(total_amount), 0) as average_sale,
        COALESCE(MAX(total_amount), 0) as highest_sale,
        COUNT(DISTINCT DATE(created_at)) as active_days
    FROM orders
    WHERE status = 'completed'";
    
    $result = $conn->query($sql);
    
    if (!$result) {
        send_error('Error retrieving sales data', 500, 'DATABASE_ERROR');
    }
    
    $data = $result->fetch_assoc();
    
    // Get monthly data for trend analysis
    $monthly_sql = "SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(id) as orders,
        SUM(total_amount) as revenue
    FROM orders
    WHERE status = 'completed'
    AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY month
    ORDER BY month ASC";
    
    $monthly_result = $conn->query($monthly_sql);
    $monthly_data = [];
    
    if ($monthly_result) {
        while ($row = $monthly_result->fetch_assoc()) {
            $monthly_data[] = [
                'month' => $row['month'],
                'month_name' => date('M Y', strtotime($row['month'] . '-01')),
                'orders' => (int)$row['orders'],
                'revenue' => (float)$row['revenue']
            ];
        }
    }
    
    $response = [
        'status' => 'success',
        'data' => [
            'summary' => [
                'total_sales' => (int)$data['total_sales'],
                'total_revenue' => (float)$data['total_revenue'],
                'average_sale' => (float)$data['average_sale'],
                'highest_sale' => (float)$data['highest_sale'],
                'active_days' => (int)$data['active_days']
            ],
            'monthly_trends' => $monthly_data
        ]
    ];
    
    send_response($response);
}

// Get top selling products
function handle_top_products($conn) {
    // Only allow GET for top products
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        send_error('Method not allowed', 405, 'METHOD_NOT_ALLOWED');
    }
    
    // Require authentication
    $user_id = require_auth($conn);
    
    // Only admins and cashiers can view sales data
    require_role($user_id, ['admin', 'cashier'], $conn);
    
    // Get limit from query parameter, default to 10
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $limit = max(1, min(50, $limit)); // Ensure limit is between 1 and 50
    
    $sql = "SELECT 
        p.id,
        p.name as product_name,
        SUM(oi.quantity) as total_sold,
        COALESCE(SUM(oi.quantity * oi.price), 0) as total_revenue,
        p.quantity as current_stock,
        p.price as current_price
    FROM products p
    LEFT JOIN order_items oi ON p.id = oi.product_id
    LEFT JOIN orders o ON oi.order_id = o.id AND o.status = 'completed'
    GROUP BY p.id, p.name, p.quantity, p.price
    ORDER BY total_sold DESC, total_revenue DESC
    LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        send_error('Error preparing database query', 500, 'DATABASE_ERROR');
    }
    
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = [
            'id' => (int)$row['id'],
            'name' => $row['product_name'],
            'total_sold' => (int)$row['total_sold'],
            'total_revenue' => (float)$row['total_revenue'],
            'current_stock' => (int)$row['current_stock'],
            'current_price' => (float)$row['current_price'],
            'profit_margin' => round(($row['total_revenue'] / max(1, $row['total_sold'])) - $row['current_price'], 2)
        ];
    }
    
    $response = [
        'status' => 'success',
        'data' => [
            'top_products' => $products,
            'count' => count($products)
        ]
    ];
    
    send_response($response);
}

// Add a new sale
function handle_add_sale($conn) {
    // Only allow POST for adding sales
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        send_error('Method not allowed', 405, 'METHOD_NOT_ALLOWED');
    }
    
    // Require authentication
    $user_id = require_auth($conn);
    
    // Only admins and cashiers can add sales
    require_role($user_id, ['admin', 'cashier'], $conn);
    
    // Get JSON data
    $data = get_json_data();
    
    // Validate required fields
    if (!isset($data['product_id']) || !isset($data['quantity'])) {
        send_error('Missing required fields: product_id, quantity', 400, 'MISSING_FIELDS');
    }
    
    $product_id = (int)$data['product_id'];
    $quantity = (int)$data['quantity'];
    
    // Validate quantity
    if ($quantity <= 0) {
        send_error('Quantity must be greater than zero', 400, 'INVALID_QUANTITY');
    }
    
    // Check if product exists and has enough stock
    $product_sql = "SELECT id, name, price, quantity as stock FROM products WHERE id = ?";
    $stmt = $conn->prepare($product_sql);
    $stmt->bind_param('i', $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        send_error('Product not found', 404, 'PRODUCT_NOT_FOUND');
    }
    
    $product = $result->fetch_assoc();
    
    if ($product['stock'] < $quantity) {
        send_error('Not enough stock available. Available: ' . $product['stock'], 400, 'INSUFFICIENT_STOCK');
    }
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Calculate total amount
        $total_amount = $quantity * $product['price'];
        $sales_channel = isset($data['sales_channel']) ? $data['sales_channel'] : 'api';
        $destination = isset($data['destination']) ? $data['destination'] : null;
        $notes = isset($data['notes']) ? $data['notes'] : null;
        
        // Create order
        $order_sql = "INSERT INTO orders (user_id, total_amount, status, sales_channel, destination, notes) 
                      VALUES (?, ?, 'completed', ?, ?, ?)";
        $stmt = $conn->prepare($order_sql);
        $stmt->bind_param('idss', $user_id, $total_amount, $sales_channel, $destination, $notes);
        
        if (!$stmt->execute()) {
            throw new Exception("Error creating order: " . $conn->error);
        }
        
        $order_id = $conn->insert_id;
        
        // Add order item
        $item_sql = "INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($item_sql);
        $stmt->bind_param('iidd', $order_id, $product_id, $quantity, $product['price']);
        
        if (!$stmt->execute()) {
            throw new Exception("Error adding order item: " . $conn->error);
        }
        
        // Update product quantity
        $update_sql = "UPDATE products SET quantity = quantity - ? WHERE id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param('ii', $quantity, $product_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Error updating product quantity: " . $conn->error);
        }
        
        // Commit transaction
        $conn->commit();
        
        $response = [
            'status' => 'success',
            'message' => 'Sale added successfully',
            'data' => [
                'order_id' => $order_id,
                'product' => $product['name'],
                'quantity' => $quantity,
                'total_amount' => $total_amount,
                'remaining_stock' => $product['stock'] - $quantity
            ]
        ];
        
        send_response($response);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        send_error($e->getMessage(), 500, 'TRANSACTION_FAILED');
    }
}

// Get sales data by date range
function handle_sales_by_date($conn) {
    // Only allow GET for sales by date
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        send_error('Method not allowed', 405, 'METHOD_NOT_ALLOWED');
    }
    
    // Require authentication
    $user_id = require_auth($conn);
    
    // Only admins and cashiers can view sales data
    require_role($user_id, ['admin', 'cashier'], $conn);
    
    // Get date range from query parameters
    $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
    $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
    
    // Validate dates
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
        send_error('Invalid date format. Use YYYY-MM-DD', 400, 'INVALID_DATE_FORMAT');
    }
    
    // Get sales by date
    $sql = "SELECT 
        DATE(created_at) as sale_date,
        COUNT(id) as order_count,
        SUM(total_amount) as daily_revenue
    FROM orders
    WHERE status = 'completed'
    AND DATE(created_at) BETWEEN ? AND ?
    GROUP BY DATE(created_at)
    ORDER BY sale_date ASC";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        send_error('Error preparing database query', 500, 'DATABASE_ERROR');
    }
    
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $sales_data = [];
    while ($row = $result->fetch_assoc()) {
        $sales_data[] = [
            'date' => $row['sale_date'],
            'orders' => (int)$row['order_count'],
            'revenue' => (float)$row['daily_revenue']
        ];
    }
    
    // Calculate period totals
    $total_orders = 0;
    $total_revenue = 0;
    
    foreach ($sales_data as $day) {
        $total_orders += $day['orders'];
        $total_revenue += $day['revenue'];
    }
    
    $response = [
        'status' => 'success',
        'data' => [
            'period' => [
                'start_date' => $start_date,
                'end_date' => $end_date,
                'total_days' => count($sales_data),
                'total_orders' => $total_orders,
                'total_revenue' => $total_revenue,
                'average_daily_revenue' => count($sales_data) > 0 ? $total_revenue / count($sales_data) : 0
            ],
            'daily_sales' => $sales_data
        ]
    ];
    
    send_response($response);
}
