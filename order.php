<?php
/**
 * Supplier Order Management System
 * 
 * Handles supplier order operations for inventory restocking including:
 * - Creating new orders to suppliers
 * - Managing order status (pending, ordered, shipped, delivered, cancelled)
 * - Order approval workflow for non-admin users
 * - Preventing modification of finalized orders (delivered/cancelled)
 * - Sequential ID management after deletions
 */

require_once 'config/db.php';
require_once 'config/auth.php';

/**
 * Reorder supplier order IDs to maintain sequential numbering after deletion
 * Ensures clean ID sequence and maintains referential integrity
 * 
 * @param mysqli $conn Database connection
 * @throws Exception If reordering fails
 */
function reorderSupplierOrderIds($conn) {
    try {  
        // Start transaction for atomic reordering operation
        $conn->autocommit(false);
        
        // Get all supplier orders ordered by current ID
        $result = $conn->query("SELECT id FROM supplier_orders ORDER BY id ASC");
        if (!$result) {
            throw new Exception("Failed to fetch supplier orders for reordering");
        }
        
        $orders = $result->fetch_all(MYSQLI_ASSOC);
        $new_id = 1;
        
        // Temporarily disable foreign key checks to allow ID updates
        $conn->query("SET FOREIGN_KEY_CHECKS = 0");
        
        foreach ($orders as $order) {
            $old_id = $order['id'];
            if ($old_id != $new_id) {
                // Update supplier order ID
                $stmt = $conn->prepare("UPDATE supplier_orders SET id = ? WHERE id = ?");
                $stmt->bind_param("ii", $new_id, $old_id);
                $stmt->execute();
            }
            $new_id++;
        }
        
        // Reset auto increment to next available number
        $conn->query("ALTER TABLE supplier_orders AUTO_INCREMENT = $new_id");
        
        // Re-enable foreign key checks
        $conn->query("SET FOREIGN_KEY_CHECKS = 1");
        
        // Commit the transaction
        $conn->commit();
        $conn->autocommit(true);
        
        return true;
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        $conn->query("SET FOREIGN_KEY_CHECKS = 1");
        $conn->autocommit(true);
        throw new Exception("Failed to reorder supplier order IDs: " . $e->getMessage());
    }
}

// check if user can see this page - only admins and store people
// learned about user roles in class
requireRole(['admin', 'store_clerk'], $conn);

$current_user_role = getCurrentUserRole($conn); // get what kind of user this is

// function to get all supplier orders from database
// this gets all the orders and shows them in a table
function getAllSupplierOrders($conn) {
    // learned about SQL joins in database class
    $sql = "SELECT so.*, p.quantity as current_stock, u.username as approved_by_name
            FROM supplier_orders so
            LEFT JOIN products p ON so.product_id = p.id
            LEFT JOIN users u ON so.approved_by = u.id
            ORDER BY so.order_date DESC";
    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

// get all products so we can order them
function getProducts($conn) {
    $sql = "SELECT id, name, quantity, price FROM products ORDER BY name ASC";
    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

// function to get suppliers
function getSuppliers($conn) {
    // check if suppliers table exists first - learned this is important
    $check_table = $conn->query("SHOW TABLES LIKE 'suppliers'");
    if ($check_table->num_rows == 0) {
        // create table if it doesn't exist (basic table structure)
        $create_table_sql = "CREATE TABLE IF NOT EXISTS suppliers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            phone VARCHAR(20) NOT NULL,
            address TEXT NULL,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        $conn->query($create_table_sql);
        
        // add some sample suppliers so we have something to work with
        $sample_suppliers = "INSERT INTO suppliers (name, email, phone, is_active) VALUES 
            ('ABC Supply Co.', 'contact@abcsupply.com', '+1-555-0101', 1),
            ('XYZ Trading', 'sales@xyztrading.com', '+1-555-0102', 1),
            ('Global Suppliers Inc.', 'info@globalsuppliers.com', '+1-555-0103', 1)";
        $conn->query($sample_suppliers);
    }
    
    // get all active suppliers
    $sql = "SELECT * FROM suppliers WHERE is_active = 1 ORDER BY name ASC";
    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

// handle form submissions when user clicks buttons
// learned about POST requests in web dev class
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        switch ($_POST['action']) { // check what action the user wants to do
            case 'create_supplier':
                // create a new supplier
                $name = $_POST['supplier_name'];
                $email = $_POST['supplier_email'];
                $phone = $_POST['supplier_phone'];
                
                $stmt = $conn->prepare("INSERT INTO suppliers (name, email, phone, is_active) VALUES (?, ?, ?, 1)");
                $stmt->bind_param("sss", $name, $email, $phone);
                if ($stmt->execute()) {
                    $success = "Supplier created successfully";
                    // refresh page to show new supplier
                    echo "<script>window.location.reload();</script>";
                } else {
                    throw new Exception("Failed to create supplier");
                }
                break;            case 'create_product':
                // add a new product to our system
                $name = $_POST['product_name'];
                $price = (float)$_POST['product_price'];
                $alert_quantity = (int)$_POST['alert_quantity'];
                
                $stmt = $conn->prepare("INSERT INTO products (name, quantity, price, alert_quantity) VALUES (?, 0, ?, ?)");
                $stmt->bind_param("sdi", $name, $price, $alert_quantity);
                if ($stmt->execute()) {
                    $success = "Product created successfully";
                    // refresh to show new product
                    echo "<script>window.location.reload();</script>";
                } else {
                    throw new Exception("Failed to create product");
                }
                break;

            case 'create_order':
                // this is the main part - creating an order from supplier
                $supplier_id = $_POST['supplier_id'];
                $product_id = $_POST['product_id'];
                $quantity_ordered = (int)$_POST['quantity_ordered'];
                $unit_price = (float)$_POST['unit_price'];
                $expected_delivery_date = $_POST['expected_delivery_date'];
                $notes = $_POST['notes'] ?? ''; // empty string if no notes
                
                // get info about supplier and product
                $supplier_result = $conn->query("SELECT * FROM suppliers WHERE id = $supplier_id");
                $supplier = $supplier_result->fetch_assoc();
                
                $product_result = $conn->query("SELECT * FROM products WHERE id = $product_id");
                $product = $product_result->fetch_assoc();
                
                // make sure we have valid supplier and product
                if (!$supplier || !$product) {
                    throw new Exception("Invalid supplier or product selected");
                }
                
                $total_amount = $quantity_ordered * $unit_price; // calculate total cost
                
                // insert the order into database
                $stmt = $conn->prepare("INSERT INTO supplier_orders (supplier_name, supplier_email, supplier_phone, product_id, product_name, quantity_ordered, unit_price, total_amount, expected_delivery_date, notes, status, approval_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending_approval')");
                $stmt->bind_param("sssissddss", 
                    $supplier['name'], 
                    $supplier['email'], 
                    $supplier['phone'], 
                    $product_id, 
                    $product['name'], 
                    $quantity_ordered, 
                    $unit_price, 
                    $total_amount, 
                    $expected_delivery_date, 
                    $notes
                );
                
                if ($stmt->execute()) {
                    $success = "Supplier order created successfully and pending approval";
                } else {
                    throw new Exception("Failed to create order");
                }
                break;case 'approve_order':
                // Only admins can approve or reject orders
                if ($current_user_role !== 'admin') {
                    throw new Exception("Only administrators can approve or reject orders");
                }
                
                $order_id = $_POST['order_id'];
                $approval_action = $_POST['approval_action']; // 'approve' or 'reject'
                $current_user_id = $_SESSION['user_id'];
                
                if ($approval_action === 'approve') {
                    $stmt = $conn->prepare("UPDATE supplier_orders SET approval_status = 'approved', approved_by = ?, approved_at = NOW(), status = 'ordered' WHERE id = ? AND approval_status = 'pending_approval'");
                    $stmt->bind_param("ii", $current_user_id, $order_id);
                    $success_message = "Order approved successfully";
                } else if ($approval_action === 'reject') {
                    $stmt = $conn->prepare("UPDATE supplier_orders SET approval_status = 'rejected', approved_by = ?, approved_at = NOW(), status = 'cancelled' WHERE id = ? AND approval_status = 'pending_approval'");
                    $stmt->bind_param("ii", $current_user_id, $order_id);
                    $success_message = "Order rejected successfully";
                } else {
                    throw new Exception("Invalid approval action");
                }
                
                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        $success = $success_message;
                    } else {
                        throw new Exception("Order not found or already processed");
                    }
                } else {
                    throw new Exception("Failed to process approval");
                }
                break;            case 'update_status':
                $order_id = $_POST['order_id'];
                $status = $_POST['status'];
                $quantity_received = isset($_POST['quantity_received']) ? (int)$_POST['quantity_received'] : 0;
                
                // Check current order status to prevent updates on final statuses
                $check_order = $conn->query("SELECT approval_status, status FROM supplier_orders WHERE id = $order_id");
                $order_row = $check_order->fetch_assoc();
                
                if (!$order_row) {
                    throw new Exception("Order not found");
                }
                
                // Prevent updates if order is already delivered or cancelled
                if ($order_row['status'] === 'delivered' || $order_row['status'] === 'cancelled') {
                    throw new Exception("Cannot update orders that are already delivered or cancelled. Only deletion is allowed.");
                }
                
                // Check if order is approved before allowing status updates (except cancellation)
                if ($order_row['approval_status'] !== 'approved' && $status !== 'cancelled') {
                    throw new Exception("Order must be approved before updating status to " . $status);
                }
                  
                $stmt = $conn->prepare("UPDATE supplier_orders SET status = ?, quantity_received = ?, actual_delivery_date = CASE WHEN ? = 'delivered' THEN CURDATE() ELSE actual_delivery_date END WHERE id = ?");
                $stmt->bind_param("sisi", $status, $quantity_received, $status, $order_id);
                  if ($stmt->execute()) {                    // If order is delivered, update inventory quantities (stacking behavior)
                    if ($status === 'delivered' && $quantity_received > 0) {
                        // Get order details for the inventory update
                        $order_result = $conn->query("SELECT product_id, product_name, supplier_name, unit_price FROM supplier_orders WHERE id = $order_id");
                        $order = $order_result->fetch_assoc();
                        
                        // Update existing product quantity by adding the received quantity
                        $update_inventory = $conn->prepare("UPDATE products SET quantity = quantity + ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                        $update_inventory->bind_param("ii", $quantity_received, $order['product_id']);
                        
                        if ($update_inventory->execute()) {
                            // Create inventory transaction record for tracking
                            $total_value = $quantity_received * $order['unit_price'];
                            $current_user = isset($_SESSION['username']) ? $_SESSION['username'] : 'system';
                            
                            // First, ensure inventory_transactions table exists
                            $check_table = $conn->query("SHOW TABLES LIKE 'inventory_transactions'");
                            if ($check_table->num_rows == 0) {
                                // Create the table if it doesn't exist
                                $create_table_sql = "CREATE TABLE IF NOT EXISTS inventory_transactions (
                                    id INT AUTO_INCREMENT PRIMARY KEY,
                                    product_id INT NOT NULL,
                                    product_name VARCHAR(255) NOT NULL,
                                    transaction_type ENUM('delivery', 'sale', 'adjustment', 'return') NOT NULL DEFAULT 'delivery',
                                    quantity INT NOT NULL,
                                    unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                                    total_value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                                    supplier_order_id INT NULL,
                                    supplier_name VARCHAR(255) NULL,
                                    batch_number VARCHAR(100) NULL,
                                    expiry_date DATE NULL,
                                    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                    notes TEXT NULL,
                                    created_by VARCHAR(100) NULL,
                                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                    
                                    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
                                    INDEX idx_product_id (product_id),
                                    INDEX idx_transaction_type (transaction_type),
                                    INDEX idx_transaction_date (transaction_date),
                                    INDEX idx_supplier_order_id (supplier_order_id)
                                )";
                                $conn->query($create_table_sql);
                            }
                            
                            // Create inventory transaction record for this delivery
                            $insert_transaction = $conn->prepare("INSERT INTO inventory_transactions (product_id, product_name, transaction_type, quantity, unit_price, total_value, supplier_order_id, supplier_name, notes, created_by) VALUES (?, ?, 'delivery', ?, ?, ?, ?, ?, 'Delivered from supplier order', ?)");
                            $insert_transaction->bind_param("isiddsss", 
                                $order['product_id'], 
                                $order['product_name'], 
                                $quantity_received, 
                                $order['unit_price'], 
                                $total_value, 
                                $order_id, 
                                $order['supplier_name'], 
                                $current_user
                            );
                            
                            if ($insert_transaction->execute()) {
                                $success = "Order delivered and inventory updated successfully. Quantity added to existing stock.";
                            } else {
                                throw new Exception("Failed to create inventory transaction record");
                            }
                        } else {
                            throw new Exception("Failed to update inventory quantities");
                        }
                    } else {
                        $success = "Order status updated successfully";
                    }
                } else {
                    throw new Exception("Failed to update order status");
                }
                break;            case 'delete_order':
                $order_id = $_POST['order_id'];
                
                $stmt = $conn->prepare("DELETE FROM supplier_orders WHERE id = ?");
                $stmt->bind_param("i", $order_id);
                
                if ($stmt->execute()) {
                    $affected_rows = $stmt->affected_rows;
                    
                    // If deletion was successful, reorder the remaining order IDs
                    if ($affected_rows > 0) {
                        reorderSupplierOrderIds($conn);
                    }
                    
                    $success = "Order deleted successfully and IDs reordered";                } else {
                    throw new Exception("Failed to delete order");
                }
                break;
                
            case 'reorder_ids':
                // manually reorder supplier order IDs
                if (reorderSupplierOrderIds($conn)) {
                    $success = "Supplier order IDs reordered successfully";
                } else {
                    throw new Exception("Failed to reorder supplier order IDs");
                }
                break;
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get all data for display
$supplier_orders = getAllSupplierOrders($conn);
$products = getProducts($conn);
$suppliers = getSuppliers($conn);?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">    <title>Supplier Orders - Inventory System</title>
    <!-- CDN Dependencies for enhanced UI -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Local stylesheets -->
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <style>        /* Enhanced button styling for better visibility and engagement */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background-color: #0066cc;
            color: white;
            padding: 10px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            margin: 3px;
            font-size: 13px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            min-width: 80px;
        }
        
        .btn:hover { 
            background-color: #0052a3; 
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .btn-sm {
            padding: 8px 12px;
            font-size: 12px;
            min-width: 70px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #0066cc, #004499);
            border: 1px solid #0052a3;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #0052a3, #003366);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #28a745, #1e7e34);
            border: 1px solid #1e7e34;
        }
        
        .btn-success:hover {
            background: linear-gradient(135deg, #218838, #155724);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #dc3545, #c82333);
            border: 1px solid #bd2130;
        }
        
        .btn-danger:hover {
            background: linear-gradient(135deg, #c82333, #a71e2a);
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #ffc107, #e0a800);
            color: #212529;
            border: 1px solid #d39e00;
        }
        
        .btn-warning:hover {
            background: linear-gradient(135deg, #e0a800, #d39e00);
            color: #212529;
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #6c757d, #545b62);
            border: 1px solid #545b62;
        }
        
        .btn-secondary:hover {
            background: linear-gradient(135deg, #5a6268, #454d55);
        }
        
        .btn i {
            margin-right: 5px;
            font-size: 14px;
        }
        
        .btn-sm i {
            margin-right: 4px;
            font-size: 12px;
        }
        
        /* Actions column specific styling */
        .actions-column {
            min-width: 180px;
            text-align: center;
            white-space: nowrap;
        }
        
        .action-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
            align-items: center;
        }
        
        .action-row {
            display: flex;
            gap: 4px;
            justify-content: center;
        }
        
        .badge {
            display: inline-block;
            padding: 6px 12px;
            font-size: 12px;
            font-weight: bold;
            border-radius: 20px;
            text-align: center;
            min-width: 120px;
        }
        
        .badge-warning {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            color: #856404;
            border: 1px solid #ffeaa7;
        }
          table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        th, td {
            border: 1px solid #e5e7eb;
            padding: 12px 8px;
            text-align: left;
            vertical-align: middle;
        }
        
        th {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            font-weight: bold;
            color: #495057;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #dee2e6;
        }
        
        tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        
        tr:hover {
            background-color: #e3f2fd;
            transition: background-color 0.2s ease;
        }
        
        /* Status badges styling */
        .status-badge, .approval-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-badge.pending {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .status-badge.ordered {
            background: linear-gradient(135deg, #cce5ff, #99d6ff);
            color: #0066cc;
            border: 1px solid #99d6ff;
        }
        
        .status-badge.shipped {
            background: linear-gradient(135deg, #e6ccff, #d9b3ff);
            color: #6600cc;
            border: 1px solid #d9b3ff;
        }
        
        .status-badge.delivered {
            background: linear-gradient(135deg, #d4edda, #a8e6a8);
            color: #155724;
            border: 1px solid #a8e6a8;
        }
        
        .status-badge.cancelled {
            background: linear-gradient(135deg, #f8d7da, #ffb3b3);
            color: #721c24;
            border: 1px solid #ffb3b3;
        }
        
        .approval-badge.pending-approval {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .approval-badge.approved {
            background: linear-gradient(135deg, #d4edda, #a8e6a8);
            color: #155724;
            border: 1px solid #a8e6a8;
        }
        
        .approval-badge.rejected {
            background: linear-gradient(135deg, #f8d7da, #ffb3b3);
            color: #721c24;
            border: 1px solid #ffb3b3;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 50px auto;
            padding: 20px;
            width: 80%;
            max-width: 500px;
            border-radius: 5px;
        }
        
        .form-group {
            margin: 10px 0;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 3px;
        }
        
        .alert {
            padding: 10px;
            margin: 10px 0;
            border-radius: 3px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* Enhanced interactive effects */
        .btn {
            position: relative;
            overflow: hidden;
        }
        
        .btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: width 0.3s ease, height 0.3s ease;
        }
        
        .btn:active::before {
            width: 300px;
            height: 300px;
        }
        
        .btn:focus {
            outline: 2px solid rgba(0, 123, 255, 0.5);
            outline-offset: 2px;
        }
        
        /* Tooltip styling */
        .btn[title] {
            position: relative;
        }
        
        .btn[title]:hover::after {
            content: attr(title);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            white-space: nowrap;
            z-index: 1000;
            margin-bottom: 5px;
        }
        
        /* Loading states */
        .btn.loading {
            pointer-events: none;
            opacity: 0.7;
        }
        
        .btn.loading::after {
            content: '';
            position: absolute;
            width: 16px;
            height: 16px;
            border: 2px solid transparent;
            border-top: 2px solid currentColor;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Success/Error feedback */
        .btn.success {
            background: linear-gradient(135deg, #28a745, #20c997) !important;
            animation: successPulse 0.6s ease;
        }
        
        .btn.error {
            background: linear-gradient(135deg, #dc3545, #e74c3c) !important;
            animation: errorShake 0.6s ease;
        }
        
        /* Final Status Text Styling */
        .text-muted {
            color: #6c757d !important;
            font-size: 12px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 8px;
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 4px;
        }
        
        .text-muted i {
            font-size: 10px;
        }
        
        /* Enhanced table styling */
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin: 1rem 0;
        }

        table th,
        table td {
            padding: 0.75rem;
            text-align: left;
            vertical-align: middle;
            border-bottom: 1px solid #e5e7eb;
        }

        table th {
            background-color: #f8fafc;
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        table tr:hover {
            background-color: #f9fafb;
        }

        /* Status badge styles */
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
        }

        .status-badge.pending {
            background-color: #fef3c7;
            color: #92400e;
        }

        .status-badge.ordered {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .status-badge.shipped {
            background-color: #e0e7ff;
            color: #3730a3;
        }

        .status-badge.delivered {
            background-color: #d1fae5;
            color: #065f46;
        }        .status-badge.cancelled {
            background-color: #fee2e2;
            color: #991b1b;
        }

        /* Approval Badge Styles */
        .approval-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
        }

        .approval-badge.pending-approval {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .approval-badge.approved {
            background-color: #d1fae5;
            color: #065f46;
            border: 1px solid #6ee7b7;
        }        .approval-badge.rejected {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        /* Warning Badge for Non-Admin Users */
        .badge-warning {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .card {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin: 1rem auto;
            max-width: 1200px;
            overflow-x: auto;
        }

        .card-body {
            padding: 1.5rem;
        }

        .order-filters {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 1rem auto;
            max-width: 1200px;
            padding: 1rem;
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .search-box {
            position: relative;
            flex: 1;
            max-width: 400px;
        }

        .search-box input {
            width: 100%;
            padding: 0.5rem 1rem 0.5rem 2.5rem;
            border: 1px solid #e5e7eb;
            border-radius: 0.375rem;
        }

        .search-box i {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
        }

        .filter-group {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 1rem auto;
            max-width: 1200px;
            padding: 1rem;
        }

        .header-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 1rem;
            border: 1px solid #e5e7eb;
            border-radius: 0.375rem;
            background: white;
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }

        .btn i {
            margin-right: 0.5rem;
        }

        .btn:hover {
            background: #f9fafb;
            border-color: #d1d5db;
        }

        .btn-primary {
            background: #1d4ed8;
            color: white;
            border: none;
        }

        .btn-primary:hover {
            background: #1e40af;
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
            border: none;
        }

        .btn-secondary:hover {
            background: #4b5563;
        }

        .btn-danger {
            background: #dc2626;
            color: white;
            border: none;
        }

        .btn-danger:hover {
            background: #b91c1c;
        }

        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 2rem;
            border-radius: 0.5rem;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 0.5rem 1rem;
            border: 1px solid #e5e7eb;
            border-radius: 0.375rem;
            font-size: 0.875rem;
        }

        .form-control:focus {
            outline: none;
            border-color: #1d4ed8;
            box-shadow: 0 0 0 3px rgba(29, 78, 216, 0.1);
        }

        .form-actions {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-end;
            margin-top: 1.5rem;
        }

        .form-text {
            font-size: 0.75rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }        .text-center {
            text-align: center;
        }

        .supplier-selection, .product-selection {
            display: flex;
            gap: 0.5rem;
            align-items: flex-end;
        }

        .supplier-selection select, .product-selection select {
            flex: 1;
        }        .supplier-selection .btn, .product-selection .btn {
            white-space: nowrap;
            margin-bottom: 0;
        }

        @keyframes successPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        @keyframes errorShake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Include Sidebar -->
        <?php 
        $current_page = 'order';
        require_once 'templates/sidebar.php'; 
        ?>

        <!-- Main Content -->
        <main class="main-content">
            <div class="content-header">
                <h1>Supplier Orders (Restocking)</h1>                <div class="header-actions">
                    <button class="btn btn-primary" onclick="openCreateOrderModal()">
                        <i class="fas fa-plus"></i> Resupply Products
                    </button>
                    <button class="btn btn-secondary" onclick="reorderSupplierOrderIds()" title="Reorder Order IDs">
                        <i class="fas fa-sort-numeric-down"></i> Reorder IDs
                    </button>
                </div>
            </div>

            <div class="order-filters">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchOrder" placeholder="Search by supplier or product...">
                </div>
                <div class="filter-group">
                    <select id="statusFilter" onchange="filterOrders()">
                        <option value="">All Status</option>
                        <option value="ordered">Ordered</option>
                        <option value="delivered">Delivered</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="filter-group">
                    <select id="approvalFilter" onchange="filterOrders()">
                        <option value="">All Approvals</option>
                        <option value="pending approval">Pending Approval</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
            </div>

            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Supplier Orders Table -->
            <div class="card">
                <div class="card-body">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Supplier Info</th>
                                <th>Product</th>
                                <th>Qty Ordered</th>
                                <th>Qty Received</th>
                                <th>Unit Price</th>
                                <th>Total Amount</th>
                                <th>Order Date</th>
                                <th>Expected Date</th>
                                <th>Approval Status</th>
                                <th>Status</th>
                                <th class="actions-column">
                                    <i class="fas fa-cogs"></i> Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($supplier_orders as $order): ?>
                            <tr>
                                <td>#<?php echo htmlspecialchars($order['id']); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($order['supplier_name']); ?></strong><br>
                                    <small>
                                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($order['supplier_email']); ?><br>
                                        <i class="fas fa-phone"></i> <?php echo htmlspecialchars($order['supplier_phone']); ?>
                                    </small>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($order['product_name']); ?><br>
                                    <small>Current Stock: <?php echo $order['current_stock'] ?? 'N/A'; ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($order['quantity_ordered']); ?></td>
                                <td><?php echo htmlspecialchars($order['quantity_received']); ?></td>
                                <td>$<?php echo number_format($order['unit_price'], 2); ?></td>
                                <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                                <td><?php echo date('M j, Y', strtotime($order['order_date'])); ?></td>
                                <td><?php echo $order['expected_delivery_date'] ? date('M j, Y', strtotime($order['expected_delivery_date'])) : 'N/A'; ?></td>
                                <td>
                                    <span class="approval-badge <?php echo strtolower(str_replace('_', '-', $order['approval_status'])); ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $order['approval_status'])); ?>
                                    </span>
                                    <?php if ($order['approved_by_name']): ?>
                                        <br><small>by <?php echo htmlspecialchars($order['approved_by_name']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo strtolower($order['status']); ?>">
                                        <?php echo ucfirst($order['status']); ?>
                                    </span>
                                </td>
                                <td class="actions-column">
                                    <div class="action-group">
                                        <?php if ($order['approval_status'] === 'pending_approval'): ?>
                                            <?php if ($current_user_role === 'admin'): ?>
                                                <div class="action-row">
                                                    <button class="btn btn-sm btn-success" onclick="approveOrder(<?php echo $order['id']; ?>, 'approve')" title="Approve Order">
                                                        <i class="fas fa-check"></i> Approve
                                                    </button>
                                                    <button class="btn btn-sm btn-danger" onclick="approveOrder(<?php echo $order['id']; ?>, 'reject')" title="Reject Order">
                                                        <i class="fas fa-times"></i> Reject
                                                    </button>
                                                </div>
                                            <?php else: ?>
                                                <div class="action-row">
                                                    <span class="badge badge-warning">
                                                        <i class="fas fa-clock"></i> Awaiting Admin Approval
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <div class="action-row">
                                                <?php if ($order['approval_status'] === 'approved' && $order['status'] !== 'delivered' && $order['status'] !== 'cancelled'): ?>
                                                    <button class="btn btn-sm btn-primary" onclick="updateOrderStatus(<?php echo $order['id']; ?>)" title="Update Order Status">
                                                        <i class="fas fa-edit"></i> Update
                                                    </button>
                                                <?php elseif ($order['status'] === 'delivered' || $order['status'] === 'cancelled'): ?>
                                                    <span class="text-muted" title="Updates not allowed for final status orders">
                                                        <i class="fas fa-lock"></i> Final Status
                                                    </span>
                                                <?php endif; ?>
                                                <button class="btn btn-sm btn-secondary" onclick="deleteOrder(<?php echo $order['id']; ?>)" title="Delete Order">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($supplier_orders)): ?>
                            <tr>
                                <td colspan="12" class="text-center">No supplier orders found</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Create Supplier Order Modal -->
    <div id="createOrderModal" class="modal">
        <div class="modal-content">
            <h2>Resupply Products</h2>
            <form method="POST" class="order-form">
                <input type="hidden" name="action" value="create_order">
                
                <div class="form-group">
                    <label for="supplier_id">Supplier</label>
                    <div class="supplier-selection">
                        <select id="supplier_id" name="supplier_id" required class="form-control">
                            <option value="">Select a supplier...</option>
                            <?php foreach ($suppliers as $supplier): ?>
                            <option value="<?php echo $supplier['id']; ?>">
                                <?php echo htmlspecialchars($supplier['name']); ?> - <?php echo htmlspecialchars($supplier['email']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="openCreateSupplierModal()">
                            <i class="fas fa-plus"></i> New Supplier
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="product_id">Product to Restock</label>
                    <div class="product-selection">
                        <select id="product_id" name="product_id" required class="form-control">
                            <option value="">Select a product...</option>
                            <?php foreach ($products as $product): ?>
                            <option value="<?php echo $product['id']; ?>">
                                <?php echo htmlspecialchars($product['name']); ?> (Current: <?php echo $product['quantity']; ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="openCreateProductModal()">
                            <i class="fas fa-plus"></i> New Product
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="quantity_ordered">Quantity to Order</label>
                    <input type="number" id="quantity_ordered" name="quantity_ordered" required min="1" class="form-control">
                </div>

                <div class="form-group">
                    <label for="unit_price">Unit Price ($)</label>
                    <input type="number" id="unit_price" name="unit_price" required min="0" step="0.01" class="form-control">
                </div>

                <div class="form-group">
                    <label for="expected_delivery_date">Expected Delivery Date</label>
                    <input type="date" id="expected_delivery_date" name="expected_delivery_date" class="form-control">
                </div>

                <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes" class="form-control" rows="3" placeholder="Additional notes or instructions..."></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Create Order</button>
                    <button type="button" class="btn btn-secondary" onclick="closeCreateOrderModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div id="updateStatusModal" class="modal">
        <div class="modal-content">
            <h2>Update Order Status</h2>
            <form method="POST" class="status-form">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="order_id" id="update_order_id">
                
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" required class="form-control">
                        <option value="pending">Pending</option>
                        <option value="ordered">Ordered</option>
                        <option value="delivered">Delivered</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>

                <div class="form-group" id="quantityReceivedGroup" style="display: none;">
                    <label for="quantity_received">Quantity Received</label>
                    <input type="number" id="quantity_received" name="quantity_received" min="0" class="form-control">
                    <small class="form-text">Enter the actual quantity received (this will update inventory)</small>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Update Status</button>
                    <button type="button" class="btn btn-secondary" onclick="closeUpdateStatusModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Create New Supplier Modal -->
    <div id="createSupplierModal" class="modal">
        <div class="modal-content">
            <h2>Add New Supplier</h2>
            <form method="POST" class="supplier-form">
                <input type="hidden" name="action" value="create_supplier">
                
                <div class="form-group">
                    <label for="supplier_name">Supplier Name</label>
                    <input type="text" id="supplier_name" name="supplier_name" required class="form-control">
                </div>

                <div class="form-group">
                    <label for="supplier_email">Email</label>
                    <input type="email" id="supplier_email" name="supplier_email" required class="form-control">
                </div>

                <div class="form-group">
                    <label for="supplier_phone">Phone Number</label>
                    <input type="tel" id="supplier_phone" name="supplier_phone" required class="form-control">
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Add Supplier</button>
                    <button type="button" class="btn btn-secondary" onclick="closeCreateSupplierModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Create New Product Modal -->
    <div id="createProductModal" class="modal">
        <div class="modal-content">
            <h2>Add New Product</h2>
            <form method="POST" class="product-form">
                <input type="hidden" name="action" value="create_product">
                
                <div class="form-group">
                    <label for="product_name">Product Name</label>
                    <input type="text" id="product_name" name="product_name" required class="form-control">
                </div>

                <div class="form-group">
                    <label for="product_price">Unit Price ($)</label>
                    <input type="number" id="product_price" name="product_price" required min="0" step="0.01" class="form-control">
                </div>

                <div class="form-group">
                    <label for="alert_quantity">Alert Quantity (Low Stock Warning)</label>
                    <input type="number" id="alert_quantity" name="alert_quantity" required min="0" class="form-control" value="10">
                    <small class="form-text">System will alert when stock falls below this level</small>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Add Product</button>
                    <button type="button" class="btn btn-secondary" onclick="closeCreateProductModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal functions
        function openCreateOrderModal() {
            document.getElementById('createOrderModal').style.display = 'block';
        }

        function closeCreateOrderModal() {
            document.getElementById('createOrderModal').style.display = 'none';
        }

        function openCreateSupplierModal() {
            document.getElementById('createSupplierModal').style.display = 'block';
        }

        function closeCreateSupplierModal() {
            document.getElementById('createSupplierModal').style.display = 'none';
        }

        function openCreateProductModal() {
            document.getElementById('createProductModal').style.display = 'block';
        }

        function closeCreateProductModal() {
            document.getElementById('createProductModal').style.display = 'none';
        }

        function openUpdateStatusModal() {
            document.getElementById('updateStatusModal').style.display = 'block';
        }

        function closeUpdateStatusModal() {
            document.getElementById('updateStatusModal').style.display = 'none';
        }

        function updateOrderStatus(orderId) {
            const button = event.target.closest('.btn');
            
            // Add visual feedback
            button.classList.add('loading');
            button.disabled = true;
            
            // Reset after showing modal
            setTimeout(() => {
                button.classList.remove('loading');
                button.disabled = false;
            }, 300);
            
            document.getElementById('update_order_id').value = orderId;
            
            // Find the current status of the order
            const orderRow = document.querySelector(`td:first-child`).parentElement;
            const currentStatus = orderRow.querySelector('.status-badge').textContent.trim().toLowerCase();
            
            // Set the current status in the modal
            document.getElementById('status').value = currentStatus;
            
            // Show quantity received field if status is delivered
            toggleQuantityReceivedField();
            
            openUpdateStatusModal();
        }

        function approveOrder(orderId, action) {
            const actionText = action === 'approve' ? 'approve' : 'reject';
            const button = event.target.closest('.btn');
            
            if (confirm(`Are you sure you want to ${actionText} this supplier order?`)) {
                // Add loading state to button
                button.classList.add('loading');
                button.disabled = true;
                
                // Create and submit form
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="approve_order">
                    <input type="hidden" name="order_id" value="${orderId}">
                    <input type="hidden" name="approval_action" value="${action}">
                `;
                document.body.appendChild(form);
                
                // Add a small delay for visual feedback
                setTimeout(() => {
                    form.submit();
                }, 300);
            }
        }

        function deleteOrder(orderId) {
            const button = event.target.closest('.btn');
            
            if (confirm('Are you sure you want to delete this supplier order? This action cannot be undone.')) {
                // Add loading state to button
                button.classList.add('loading');
                button.disabled = true;
                
                // Create and submit form
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_order">
                    <input type="hidden" name="order_id" value="${orderId}">
                `;
                document.body.appendChild(form);
                
                // Add a small delay for visual feedback
                setTimeout(() => {
                    form.submit();
                }, 300);
            }
        }

        // Show/hide quantity received field based on status
        function toggleQuantityReceivedField() {
            const status = document.getElementById('status').value;
            const quantityGroup = document.getElementById('quantityReceivedGroup');
            
            if (status === 'delivered') {
                quantityGroup.style.display = 'block';
                document.getElementById('quantity_received').required = true;
            } else {
                quantityGroup.style.display = 'none';
                document.getElementById('quantity_received').required = false;
            }
        }

        // Add event listener for status change
        document.getElementById('status').addEventListener('change', toggleQuantityReceivedField);

        // Search functionality
        document.getElementById('searchOrder').addEventListener('keyup', function() {
            const searchValue = this.value.toLowerCase();
            const rows = document.querySelectorAll('tbody tr');

            rows.forEach(row => {
                if (row.children.length > 1) {
                    const supplierName = row.children[1].textContent.toLowerCase();
                    const productName = row.children[2].textContent.toLowerCase();
                    const orderId = row.children[0].textContent.toLowerCase();
                    const approvalStatus = row.children[9].textContent.toLowerCase();
                    const status = row.children[10].textContent.toLowerCase();
                    
                    const shouldShow = supplierName.includes(searchValue) || 
                                     productName.includes(searchValue) || 
                                     orderId.includes(searchValue) ||
                                     approvalStatus.includes(searchValue) ||
                                     status.includes(searchValue);
                    row.style.display = shouldShow ? '' : 'none';
                }
            });
        });

        // Filter by status (includes both approval status and order status)
        function filterOrders() {
            const statusFilter = document.getElementById('statusFilter').value.toLowerCase();
            const approvalFilter = document.getElementById('approvalFilter').value.toLowerCase();
            const rows = document.querySelectorAll('tbody tr');

            rows.forEach(row => {
                if (row.children.length > 1) {
                    const approvalStatus = row.children[9].textContent.toLowerCase();
                    const orderStatus = row.children[10].textContent.toLowerCase();
                    
                    const statusMatch = !statusFilter || orderStatus.includes(statusFilter);
                    const approvalMatch = !approvalFilter || approvalStatus.includes(approvalFilter);
                    
                    const shouldShow = statusMatch && approvalMatch;
                    row.style.display = shouldShow ? '' : 'none';
                }
            });
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target == document.getElementById('createOrderModal')) {
                closeCreateOrderModal();
            }
            if (event.target == document.getElementById('updateStatusModal')) {
                closeUpdateStatusModal();
            }
            if (event.target == document.getElementById('createSupplierModal')) {
                closeCreateSupplierModal();
            }
            if (event.target == document.getElementById('createProductModal')) {
                closeCreateProductModal();
            }
        }

        // Enhanced button interactions and feedback
        document.addEventListener('DOMContentLoaded', function() {
            // Add ripple effect to all buttons
            const buttons = document.querySelectorAll('.btn');
            buttons.forEach(button => {
                button.addEventListener('click', function(e) {
                    const ripple = document.createElement('span');
                    const rect = this.getBoundingClientRect();
                    const size = Math.max(rect.width, rect.height);
                    const x = e.clientX - rect.left - size / 2;
                    const y = e.clientY - rect.top - size / 2;
                    
                    ripple.style.width = ripple.style.height = size + 'px';
                    ripple.style.left = x + 'px';
                    ripple.style.top = y + 'px';
                    ripple.classList.add('ripple');
                    
                    this.appendChild(ripple);
                    
                    setTimeout(() => {
                        ripple.remove();
                    }, 600);
                });
            });
            
            // Add hover sound effect (optional)
            buttons.forEach(button => {
                button.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-1px) scale(1.02)';
                });
                
                button.addEventListener('mouseleave', function() {
                    if (!this.classList.contains('loading')) {
                        this.style.transform = 'translateY(0) scale(1)';
                    }
                });
            });
            
            // Enhanced status badges with pulse animation for pending items
            const pendingBadges = document.querySelectorAll('.status-badge.pending, .approval-badge.pending-approval');
            pendingBadges.forEach(badge => {
                badge.style.animation = 'pendingPulse 2s ease-in-out infinite';
            });
        });
        
        // Add custom CSS for ripple effect
        const style = document.createElement('style');
        style.textContent = `
            .btn {
                position: relative;
                overflow: hidden;
            }
            
            .ripple {
                position: absolute;
                background: rgba(255, 255, 255, 0.6);
                border-radius: 50%;
                transform: scale(0);
                animation: rippleEffect 0.6s linear;
                pointer-events: none;
            }
            
            @keyframes rippleEffect {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
            
            @keyframes pendingPulse {
                0%, 100% {
                    opacity: 1;
                    transform: scale(1);
                }
                50% {
                    opacity: 0.8;
                    transform: scale(1.02);
                }
            }        `;
        document.head.appendChild(style);

        // Function to reorder supplier order IDs
        async function reorderSupplierOrderIds() {
            if (confirm('Are you sure you want to reorder all supplier order IDs? This will renumber all orders sequentially.')) {
                try {
                    const formData = new FormData();
                    formData.append('action', 'reorder_ids');
                    
                    const response = await fetch('order.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    if (response.ok) {
                        window.location.reload();
                    } else {
                        throw new Error('Failed to reorder supplier order IDs');
                    }
                } catch (error) {
                    console.error('Error reordering supplier order IDs:', error);
                    alert('Error reordering supplier order IDs: ' + error.message);
                }
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
