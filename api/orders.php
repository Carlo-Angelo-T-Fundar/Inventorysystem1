<?php
// Orders API Endpoints
require_once 'config.php';

// Route handling
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

switch ($action) {
    case 'list':
        handle_list_orders($conn);
        break;
    case 'get':
        handle_get_order($conn);
        break;
    case 'create':
        handle_create_order($conn);
        break;
    case 'update_status':
        handle_update_order_status($conn);
        break;
    default:
        send_error('Invalid order action', 404, 'INVALID_ENDPOINT');
}

// List all orders
function handle_list_orders($conn) {
    // Only allow GET for listing orders
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        send_error('Method not allowed', 405, 'METHOD_NOT_ALLOWED');
    }
    
    // Require authentication
    $user_id = require_auth($conn);
    
    // Only admins, store clerks, and cashiers can view orders
    require_role($user_id, ['admin', 'store_clerk', 'cashier'], $conn);
    
    // Get query parameters for filtering
    $status = isset($_GET['status']) ? $_GET['status'] : '';
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 50;
    $offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;
      // Build query - only select columns that exist
    $sql = "SELECT o.* FROM orders o";
    $params = [];
    $types = "";
    
    // Where conditions
    $conditions = [];
      if (!empty($status)) {
        $valid_statuses = ['pending', 'processing', 'completed', 'cancelled'];
        if (in_array($status, $valid_statuses)) {
            $conditions[] = "o.status = ?";
            $params[] = $status;
            $types .= "s";
        }
    }
    
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }
    
    // Order by
    $sql .= " ORDER BY o.id ASC";
    
    // Limit
    $sql .= " LIMIT ?, ?";
    $params[] = $offset;
    $params[] = $limit;
    $types .= "ii";
    
    // Prepare and execute query
    $stmt = $conn->prepare($sql);
    
    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $orders = [];
    while ($row = $result->fetch_assoc()) {
        // Get order items
        $items_stmt = $conn->prepare("
            SELECT oi.*, p.name as product_name 
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
        ");
        $items_stmt->bind_param("i", $row['id']);
        $items_stmt->execute();
        $items_result = $items_stmt->get_result();
        
        $items = [];
        while ($item = $items_result->fetch_assoc()) {
            $items[] = [
                'id' => (int) $item['id'],
                'product_id' => (int) $item['product_id'],
                'product_name' => $item['product_name'],
                'quantity' => (int) $item['quantity'],
                'price' => (float) $item['price'],
                'subtotal' => (float) ($item['quantity'] * $item['price'])
            ];
        }        $orders[] = [
            'id' => (int) $row['id'],
            'total_amount' => (float) ($row['total_amount'] ?? 0),
            'status' => $row['status'] ?? 'pending',
            'sales_channel' => $row['sales_channel'] ?? 'store',
            'destination' => $row['destination'] ?? 'Lalitpur',
            'created_at' => $row['created_at'] ?? date('Y-m-d H:i:s'),
            'updated_at' => $row['updated_at'] ?? date('Y-m-d H:i:s'),
            'items' => $items
        ];
    }
    
    // Get total count for pagination
    $count_sql = "SELECT COUNT(*) as total FROM orders";
    if (!empty($conditions)) {
        $count_sql .= " WHERE " . implode(" AND ", $conditions);
    }
    
    $count_stmt = $conn->prepare($count_sql);
    
    // Bind parameters without the limit and offset
    $count_params = array_slice($params, 0, -2);
    $count_types = substr($types, 0, -2);
    
    if (!empty($count_types)) {
        $count_stmt->bind_param($count_types, ...$count_params);
    }
    
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $total = $count_result->fetch_assoc()['total'];
    
    send_response([
        'status' => 'success',
        'data' => [
            'orders' => $orders,
            'pagination' => [
                'total' => (int) $total,
                'limit' => $limit,
                'offset' => $offset
            ]
        ]
    ]);
}

// Get a single order
function handle_get_order($conn) {
    // Only allow GET for getting an order
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        send_error('Method not allowed', 405, 'METHOD_NOT_ALLOWED');
    }
    
    // Require authentication
    $user_id = require_auth($conn);
    
    // Only admins, store clerks, and cashiers can view order details
    require_role($user_id, ['admin', 'store_clerk', 'cashier'], $conn);
    
    // Check if order ID is provided
    if (!isset($_GET['id'])) {
        send_error('Order ID is required', 400, 'MISSING_ORDER_ID');
    }
    
    $order_id = (int) $_GET['id'];
      // Get order - only select existing columns
    $stmt = $conn->prepare("
        SELECT o.*
        FROM orders o
        WHERE o.id = ?
    ");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        send_error('Order not found', 404, 'ORDER_NOT_FOUND');
    }
    
    $order = $result->fetch_assoc();
    
    // Get order items
    $items_stmt = $conn->prepare("
        SELECT oi.*, p.name as product_name 
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
    ");
    $items_stmt->bind_param("i", $order_id);
    $items_stmt->execute();
    $items_result = $items_stmt->get_result();
    
    $items = [];
    while ($item = $items_result->fetch_assoc()) {
        $items[] = [
            'id' => (int) $item['id'],
            'product_id' => (int) $item['product_id'],
            'product_name' => $item['product_name'],
            'quantity' => (int) $item['quantity'],
            'price' => (float) $item['price'],
            'subtotal' => (float) ($item['quantity'] * $item['price'])
        ];
    }    // Format response
    $response = [
        'id' => (int) $order['id'],
        'total_amount' => (float) ($order['total_amount'] ?? 0),
        'status' => $order['status'] ?? 'pending',
        'sales_channel' => $order['sales_channel'] ?? 'store',
        'destination' => $order['destination'] ?? 'Lalitpur',
        'created_at' => $order['created_at'] ?? date('Y-m-d H:i:s'),
        'updated_at' => $order['updated_at'] ?? date('Y-m-d H:i:s'),
        'items' => $items
    ];
    
    send_response([
        'status' => 'success',
        'data' => $response
    ]);
}

// Create a new order
function handle_create_order($conn) {
    // Only allow POST for creating orders
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        send_error('Method not allowed', 405, 'METHOD_NOT_ALLOWED');
    }
    
    // Require authentication
    $user_id = require_auth($conn);
    
    // Only admins, store clerks, and cashiers can create orders
    require_role($user_id, ['admin', 'store_clerk', 'cashier'], $conn);
    
    // Get JSON data from request body
    $data = get_json_data();
    
    // Check required fields
    if (empty($data['items']) || !is_array($data['items'])) {
        send_error('Order items are required', 400, 'MISSING_ITEMS');
    }
    
    // Start a transaction
    $conn->begin_transaction();
    
    try {
        // Get status
        $status = isset($data['status']) ? $data['status'] : 'pending';
          // Validate status - match frontend expectations
        $valid_statuses = ['pending', 'processing', 'completed', 'cancelled'];
        if (!in_array($status, $valid_statuses)) {
            throw new Exception('Invalid status. Must be one of: ' . implode(', ', $valid_statuses));
        }        // Get optional fields
        $sales_channel = isset($data['sales_channel']) ? $data['sales_channel'] : 'store';
        $destination = isset($data['destination']) ? $data['destination'] : 'Lalitpur';
        
        // Create order record with all supported fields
        $stmt = $conn->prepare("INSERT INTO orders (status, sales_channel, destination) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $status, $sales_channel, $destination);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to create order: ' . $conn->error);
        }
        
        $order_id = $conn->insert_id;
        
        // Process order items
        $total_amount = 0;
        
        foreach ($data['items'] as $item) {
            if (empty($item['product_id']) || empty($item['quantity'])) {
                throw new Exception('Product ID and quantity are required for each item');
            }
            
            $product_id = (int) $item['product_id'];
            $quantity = (int) $item['quantity'];
            
            if ($quantity <= 0) {
                throw new Exception('Quantity must be greater than zero');
            }
            
            // Get product price and check inventory
            $product_stmt = $conn->prepare("SELECT price, quantity FROM products WHERE id = ?");
            $product_stmt->bind_param("i", $product_id);
            $product_stmt->execute();
            $product_result = $product_stmt->get_result();
            
            if ($product_result->num_rows === 0) {
                throw new Exception("Product with ID $product_id not found");
            }
            
            $product = $product_result->fetch_assoc();
            
            if ($product['quantity'] < $quantity) {
                throw new Exception("Insufficient inventory for product ID $product_id. Available: {$product['quantity']}, Requested: $quantity");
            }
            
            // Use price from request if provided, otherwise use product price
            $price = isset($item['price']) ? (float) $item['price'] : (float) $product['price'];
            
            // Add order item
            $item_stmt = $conn->prepare("
                INSERT INTO order_items (order_id, product_id, quantity, price)
                VALUES (?, ?, ?, ?)
            ");
            $item_stmt->bind_param("iiid", $order_id, $product_id, $quantity, $price);
            
            if (!$item_stmt->execute()) {
                throw new Exception('Failed to add order item: ' . $conn->error);
            }
            
            // Update inventory
            $new_quantity = $product['quantity'] - $quantity;
            $update_stmt = $conn->prepare("UPDATE products SET quantity = ? WHERE id = ?");
            $update_stmt->bind_param("ii", $new_quantity, $product_id);
            
            if (!$update_stmt->execute()) {
                throw new Exception('Failed to update inventory: ' . $conn->error);
            }
            
            // Add to total
            $total_amount += $price * $quantity;
        }
        
        // Update order total
        $update_total_stmt = $conn->prepare("UPDATE orders SET total_amount = ? WHERE id = ?");
        $update_total_stmt->bind_param("di", $total_amount, $order_id);
        
        if (!$update_total_stmt->execute()) {
            throw new Exception('Failed to update order total: ' . $conn->error);
        }
        
        // Commit transaction
        $conn->commit();
          // Return success response with complete order data
        send_response([
            'status' => 'success',
            'message' => 'Order created successfully',
            'data' => [
                'order_id' => $order_id,
                'total_amount' => $total_amount,
                'status' => $status,
                'sales_channel' => $sales_channel,
                'destination' => $destination,
                'items_count' => count($data['items'])
            ]
        ], 201);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        send_error($e->getMessage(), 400, 'ORDER_CREATION_FAILED');
    }
}

// Update order status
function handle_update_order_status($conn) {
    // Only allow POST or PUT for updating order status
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'PUT') {
        send_error('Method not allowed', 405, 'METHOD_NOT_ALLOWED');
    }
    
    // Require authentication
    $user_id = require_auth($conn);
    
    // Only admins, store clerks, and cashiers can update order status
    require_role($user_id, ['admin', 'store_clerk', 'cashier'], $conn);
    
    // Get JSON data from request body
    $data = get_json_data();
    
    // Check if order ID is provided
    $order_id = isset($_GET['id']) ? (int) $_GET['id'] : (isset($data['order_id']) ? (int) $data['order_id'] : null);
    
    if (!$order_id) {
        send_error('Order ID is required', 400, 'MISSING_ORDER_ID');
    }
    
    // Check if status is provided
    if (empty($data['status'])) {
        send_error('Status is required', 400, 'MISSING_STATUS');
    }
    
    $status = $data['status'];
      // Validate status - match frontend expectations
    $valid_statuses = ['pending', 'processing', 'completed', 'cancelled'];
    if (!in_array($status, $valid_statuses)) {
        send_error('Invalid status. Must be one of: ' . implode(', ', $valid_statuses), 400, 'INVALID_STATUS');
    }
    
    // Check if order exists
    $check_stmt = $conn->prepare("SELECT id FROM orders WHERE id = ?");
    $check_stmt->bind_param("i", $order_id);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows === 0) {
        send_error('Order not found', 404, 'ORDER_NOT_FOUND');
    }
    
    // Update order status
    $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $order_id);
    
    if (!$stmt->execute()) {
        send_error('Failed to update order status: ' . $conn->error, 500, 'DATABASE_ERROR');
    }
    
    send_response([
        'status' => 'success',
        'message' => 'Order status updated successfully',
        'data' => [
            'order_id' => $order_id,
            'status' => $status
        ]
    ]);
}
?>
