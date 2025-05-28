<?php
// Inventory API Endpoints
require_once 'config.php';

// Route handling
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

switch ($action) {
    case 'list':
        handle_list_products($conn);
        break;
    case 'get':
        handle_get_product($conn);
        break;
    case 'create':
        handle_create_product($conn);
        break;
    case 'update':
        handle_update_product($conn);
        break;
    case 'delete':
        handle_delete_product($conn);
        break;
    default:
        send_error('Invalid inventory action', 404, 'INVALID_ENDPOINT');
}

// List all products
function handle_list_products($conn) {
    // Only allow GET for listing products
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        send_error('Method not allowed', 405, 'METHOD_NOT_ALLOWED');
    }
    
    // Require authentication
    $user_id = require_auth($conn);
    
    // All authenticated users can view products
    
    // Get query parameters for filtering
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $low_stock = isset($_GET['low_stock']) && $_GET['low_stock'] === 'true';
    $sort = isset($_GET['sort']) ? $_GET['sort'] : 'name';
    $order = isset($_GET['order']) && strtolower($_GET['order']) === 'desc' ? 'DESC' : 'ASC';
    
    // Build query
    $sql = "SELECT * FROM products";
    $params = [];
    $types = "";
    
    // Where conditions
    $conditions = [];
    
    if (!empty($search)) {
        $conditions[] = "name LIKE ?";
        $params[] = "%$search%";
        $types .= "s";
    }
    
    if ($low_stock) {
        $conditions[] = "quantity <= alert_quantity";
    }
    
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }
    
    // Order by
    $valid_sort_columns = ['name', 'quantity', 'price', 'created_at'];
    if (!in_array($sort, $valid_sort_columns)) {
        $sort = 'name';
    }
    
    $sql .= " ORDER BY $sort $order";
    
    // Prepare and execute query
    $stmt = $conn->prepare($sql);
    
    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'quantity' => (int) $row['quantity'],
            'alert_quantity' => (int) $row['alert_quantity'],
            'price' => (float) $row['price'],
            'status' => $row['quantity'] <= 0 ? 'out_of_stock' : 
                       ($row['quantity'] <= $row['alert_quantity'] ? 'low_stock' : 'in_stock'),
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ];
    }
    
    send_response([
        'status' => 'success',
        'data' => $products
    ]);
}

// Get a single product
function handle_get_product($conn) {
    // Only allow GET for getting a product
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        send_error('Method not allowed', 405, 'METHOD_NOT_ALLOWED');
    }
    
    // Require authentication
    $user_id = require_auth($conn);
    
    // All authenticated users can view product details
    
    // Check if product ID is provided
    if (!isset($_GET['id'])) {
        send_error('Product ID is required', 400, 'MISSING_PRODUCT_ID');
    }
    
    $product_id = (int) $_GET['id'];
    
    // Get product
    $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        send_error('Product not found', 404, 'PRODUCT_NOT_FOUND');
    }
    
    $product = $result->fetch_assoc();
    
    // Format response
    $response = [
        'id' => $product['id'],
        'name' => $product['name'],
        'quantity' => (int) $product['quantity'],
        'alert_quantity' => (int) $product['alert_quantity'],
        'price' => (float) $product['price'],
        'status' => $product['quantity'] <= 0 ? 'out_of_stock' : 
                   ($product['quantity'] <= $product['alert_quantity'] ? 'low_stock' : 'in_stock'),
        'created_at' => $product['created_at'],
        'updated_at' => $product['updated_at']
    ];
    
    send_response([
        'status' => 'success',
        'data' => $response
    ]);
}

// Create a new product
function handle_create_product($conn) {
    // Only allow POST for creating products
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        send_error('Method not allowed', 405, 'METHOD_NOT_ALLOWED');
    }
    
    // Require authentication
    $user_id = require_auth($conn);
    
    // Only admins, store clerks, and suppliers can create products
    require_role($user_id, ['admin', 'store_clerk', 'supplier'], $conn);
    
    // Get JSON data from request body
    $data = get_json_data();
    
    // Check required fields
    if (empty($data['name']) || !isset($data['quantity']) || !isset($data['price'])) {
        send_error('Name, quantity, and price are required', 400, 'MISSING_FIELDS');
    }
    
    $name = $data['name'];
    $quantity = (int) $data['quantity'];
    $alert_quantity = isset($data['alert_quantity']) ? (int) $data['alert_quantity'] : 10;
    $price = (float) $data['price'];
    
    // Validate data
    if ($quantity < 0) {
        send_error('Quantity cannot be negative', 400, 'INVALID_QUANTITY');
    }
    
    if ($alert_quantity < 0) {
        send_error('Alert quantity cannot be negative', 400, 'INVALID_ALERT_QUANTITY');
    }
    
    if ($price < 0) {
        send_error('Price cannot be negative', 400, 'INVALID_PRICE');
    }
    
    // Check if product name already exists
    $stmt = $conn->prepare("SELECT id FROM products WHERE name = ?");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        send_error('Product with this name already exists', 409, 'PRODUCT_EXISTS');
    }
    
    // Create product
    $stmt = $conn->prepare("INSERT INTO products (name, quantity, alert_quantity, price) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("siid", $name, $quantity, $alert_quantity, $price);
    
    if (!$stmt->execute()) {
        send_error('Failed to create product: ' . $conn->error, 500, 'DATABASE_ERROR');
    }
    
    $new_product_id = $conn->insert_id;
    
    send_response([
        'status' => 'success',
        'message' => 'Product created successfully',
        'data' => [
            'id' => $new_product_id,
            'name' => $name,
            'quantity' => $quantity,
            'alert_quantity' => $alert_quantity,
            'price' => $price,
            'status' => $quantity <= 0 ? 'out_of_stock' : 
                       ($quantity <= $alert_quantity ? 'low_stock' : 'in_stock')
        ]
    ], 201);
}

// Update an existing product
function handle_update_product($conn) {
    // Only allow POST or PUT for updating products
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'PUT') {
        send_error('Method not allowed', 405, 'METHOD_NOT_ALLOWED');
    }
    
    // Require authentication
    $user_id = require_auth($conn);
    
    // Only admins, store clerks, and suppliers can update products
    require_role($user_id, ['admin', 'store_clerk', 'supplier'], $conn);
    
    // Get JSON data from request body
    $data = get_json_data();
    
    // Check if product ID is provided
    $product_id = isset($_GET['id']) ? (int) $_GET['id'] : (isset($data['id']) ? (int) $data['id'] : null);
    
    if (!$product_id) {
        send_error('Product ID is required', 400, 'MISSING_PRODUCT_ID');
    }
    
    // Get current product data
    $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        send_error('Product not found', 404, 'PRODUCT_NOT_FOUND');
    }
    
    $product = $result->fetch_assoc();
    
    // Prepare update data
    $name = isset($data['name']) ? $data['name'] : $product['name'];
    $quantity = isset($data['quantity']) ? (int) $data['quantity'] : (int) $product['quantity'];
    $alert_quantity = isset($data['alert_quantity']) ? (int) $data['alert_quantity'] : (int) $product['alert_quantity'];
    $price = isset($data['price']) ? (float) $data['price'] : (float) $product['price'];
    
    // Validate data
    if ($quantity < 0) {
        send_error('Quantity cannot be negative', 400, 'INVALID_QUANTITY');
    }
    
    if ($alert_quantity < 0) {
        send_error('Alert quantity cannot be negative', 400, 'INVALID_ALERT_QUANTITY');
    }
    
    if ($price < 0) {
        send_error('Price cannot be negative', 400, 'INVALID_PRICE');
    }
    
    // Check if new product name already exists
    if ($name !== $product['name']) {
        $stmt = $conn->prepare("SELECT id FROM products WHERE name = ? AND id != ?");
        $stmt->bind_param("si", $name, $product_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            send_error('Product with this name already exists', 409, 'PRODUCT_EXISTS');
        }
    }
    
    // Update product
    $stmt = $conn->prepare("UPDATE products SET name = ?, quantity = ?, alert_quantity = ?, price = ? WHERE id = ?");
    $stmt->bind_param("siidi", $name, $quantity, $alert_quantity, $price, $product_id);
    
    if (!$stmt->execute()) {
        send_error('Failed to update product: ' . $conn->error, 500, 'DATABASE_ERROR');
    }
    
    send_response([
        'status' => 'success',
        'message' => 'Product updated successfully',
        'data' => [
            'id' => $product_id,
            'name' => $name,
            'quantity' => $quantity,
            'alert_quantity' => $alert_quantity,
            'price' => $price,
            'status' => $quantity <= 0 ? 'out_of_stock' : 
                       ($quantity <= $alert_quantity ? 'low_stock' : 'in_stock')
        ]
    ]);
}

// Delete a product
function handle_delete_product($conn) {
    // Only allow DELETE or POST for deleting products
    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        send_error('Method not allowed', 405, 'METHOD_NOT_ALLOWED');
    }
    
    // Require authentication
    $user_id = require_auth($conn);
    
    // Only admins and store clerks can delete products
    require_role($user_id, ['admin', 'store_clerk'], $conn);
    
    // Check if product ID is provided
    $product_id = isset($_GET['id']) ? (int) $_GET['id'] : null;
    
    if (!$product_id) {
        // Try to get it from POST data if it's a POST request
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = get_json_data();
            $product_id = isset($data['id']) ? (int) $data['id'] : null;
        }
        
        if (!$product_id) {
            send_error('Product ID is required', 400, 'MISSING_PRODUCT_ID');
        }
    }
    
    // Delete product
    $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
    $stmt->bind_param("i", $product_id);
    
    if (!$stmt->execute()) {
        send_error('Failed to delete product: ' . $conn->error, 500, 'DATABASE_ERROR');
    }
    
    if ($stmt->affected_rows === 0) {
        send_error('Product not found', 404, 'PRODUCT_NOT_FOUND');
    }
    
    send_response([
        'status' => 'success',
        'message' => 'Product deleted successfully'
    ]);
}
