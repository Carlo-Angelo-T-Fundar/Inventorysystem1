-- Database Initialization Script for inventory_db
-- Run this FIRST before trying other SQL commands

-- Step 1: Create the database
CREATE DATABASE IF NOT EXISTS inventory_db;

-- Step 2: Use the database
USE inventory_db;

-- Step 3: Create all required tables

-- Products table
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    quantity INT NOT NULL DEFAULT 0,
    alert_quantity INT NOT NULL DEFAULT 10,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    role ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Orders table
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT 1,
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status ENUM('pending','processing','completed','cancelled') NOT NULL DEFAULT 'pending',
    sales_channel VARCHAR(50) DEFAULT 'Store',
    destination VARCHAR(255) DEFAULT 'Lagao',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_status (status),
    KEY idx_created_at (created_at),
    KEY idx_sales_channel (sales_channel),
    KEY idx_order_status_date (status, created_at)
);

-- Order items table
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_order_id (order_id),
    KEY idx_product_id (product_id)
);

-- Inventory transactions table
CREATE TABLE IF NOT EXISTS inventory_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    transaction_type ENUM('sale', 'purchase', 'adjustment', 'return') NOT NULL,
    quantity_change INT NOT NULL,
    previous_quantity INT NOT NULL,
    new_quantity INT NOT NULL,
    unit_price DECIMAL(10,2) DEFAULT 0.00,
    total_value DECIMAL(10,2) DEFAULT 0.00,
    reference_type VARCHAR(50) DEFAULT NULL,
    reference_id INT DEFAULT NULL,
    notes TEXT,
    user_id INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_product_id (product_id),
    KEY idx_transaction_type (transaction_type),
    KEY idx_created_at (created_at)
);

-- Supplier orders table
CREATE TABLE IF NOT EXISTS supplier_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_name VARCHAR(255) NOT NULL,
    supplier_email VARCHAR(255) NOT NULL,
    supplier_phone VARCHAR(50) NOT NULL,
    product_id INT NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    quantity_ordered INT NOT NULL,
    quantity_received INT DEFAULT 0,
    unit_price DECIMAL(10,2) NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    order_date DATE NOT NULL,
    expected_delivery_date DATE,
    actual_delivery_date DATE,
    status ENUM('pending', 'ordered', 'delivered', 'cancelled') DEFAULT 'pending',
    notes TEXT,
    approval_status ENUM('pending_approval', 'approved', 'rejected') DEFAULT 'pending_approval',
    approved_by INT DEFAULT NULL,
    approved_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_status (status),
    KEY idx_order_date (order_date),
    KEY idx_product_id (product_id)
);

-- API tokens table
CREATE TABLE IF NOT EXISTS api_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    expiry DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_user_id (user_id),
    KEY idx_token (token),
    KEY idx_expiry (expiry)
);

-- User activity logs table
CREATE TABLE IF NOT EXISTS user_activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    username VARCHAR(50) NOT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_user_id (user_id),
    KEY idx_timestamp (timestamp),
    KEY idx_action (action)
);

-- Step 4: Insert default admin user if not exists
INSERT IGNORE INTO users (username, password, email, role) 
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@example.com', 'admin');

-- Step 5: Insert sample products if table is empty
INSERT INTO products (name, quantity, alert_quantity, price) 
SELECT * FROM (
    SELECT 'Product 1' as name, 15 as quantity, 10 as alert_quantity, 29.99 as price
    UNION ALL SELECT 'Product 2', 8, 10, 19.99
    UNION ALL SELECT 'Product 3', 5, 10, 39.99
    UNION ALL SELECT 'Product 4', 20, 10, 49.99
    UNION ALL SELECT 'Product 5', 3, 10, 59.99
) as tmp
WHERE NOT EXISTS (SELECT 1 FROM products LIMIT 1);

-- Step 6: Insert sample orders if table is empty
INSERT INTO orders (user_id, total_amount, status, sales_channel, destination, created_at) 
SELECT * FROM (
    SELECT 1 as user_id, 1049.98 as total_amount, 'completed' as status, 'online' as sales_channel, 'Kathmandu' as destination, '2024-01-15 10:30:00' as created_at
    UNION ALL SELECT 1, 179.98, 'completed', 'store', 'Lalitpur', '2024-01-15 14:20:00'
    UNION ALL SELECT 1, 599.99, 'completed', 'online', 'Pokhara', '2024-01-16 09:15:00'
    UNION ALL SELECT 1, 89.99, 'completed', 'store', 'Lalitpur', '2024-01-16 16:45:00'
    UNION ALL SELECT 1, 1299.98, 'completed', 'online', 'Biratnagar', '2024-01-17 11:20:00'
    UNION ALL SELECT 1, 249.99, 'completed', 'store', 'Chitwan', '2024-01-18 13:30:00'
    UNION ALL SELECT 1, 899.99, 'completed', 'online', 'Butwal', '2024-01-19 16:00:00'
    UNION ALL SELECT 1, 159.98, 'completed', 'store', 'Dharan', '2024-01-20 11:45:00'
    UNION ALL SELECT 1, 75.99, 'pending', 'online', 'Nepalgunj', NOW()
) as tmp
WHERE NOT EXISTS (SELECT 1 FROM orders LIMIT 1);

-- Step 7: Verify tables were created
SHOW TABLES;

-- Step 8: Display success message
SELECT 'Database and tables created successfully!' as Status;
