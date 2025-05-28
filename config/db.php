<?php
// Load environment-specific configuration
$env_config = __DIR__ . '/config.php';
if (file_exists($env_config)) {
    require_once $env_config;
} else {
    // Default database configuration
    $host = "localhost";
    $username = "root";
    $password = "Carlogelo621";  // Empty password by default for local development
    $database = "inventory_db";
}

$conn = new mysqli($host, $username, $password);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if not exists
$sql = "CREATE DATABASE IF NOT EXISTS inventory_db";
if ($conn->query($sql) === FALSE) {
    die("Error creating database: " . $conn->error);
}

// Select the database
$conn->select_db($database);

// Create products table if not exists
$sql = "CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    quantity INT NOT NULL DEFAULT 0,
    alert_quantity INT NOT NULL DEFAULT 10,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === FALSE) {
    die("Error creating products table: " . $conn->error);
}

// Add sample products if table is empty
$result = $conn->query("SELECT COUNT(*) as count FROM products");
$row = $result->fetch_assoc();

if ($row['count'] == 0) {
    $sample_products = "INSERT INTO products (name, quantity, alert_quantity, price) VALUES 
        ('Product 1', 15, 10, 29.99),
        ('Product 2', 8, 10, 19.99),
        ('Product 3', 5, 10, 39.99),
        ('Product 4', 20, 10, 49.99),
        ('Product 5', 3, 10, 59.99)";
    
    if ($conn->query($sample_products) === FALSE) {
        die("Error inserting sample products: " . $conn->error);
    }
}

// Create items table
$sql = "CREATE TABLE IF NOT EXISTS items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === FALSE) {
    die("Error creating table: " . $conn->error);
}

// Create users table
$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL,
    role ENUM('admin', 'supplier', 'store_clerk', 'cashier') NOT NULL DEFAULT 'cashier',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === FALSE) {
    die("Error creating users table: " . $conn->error);
}

// Add role column to existing users table if it doesn't exist
$check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'role'");
if ($check_column->num_rows == 0) {
    $alter_sql = "ALTER TABLE users ADD COLUMN role ENUM('admin', 'supplier', 'store_clerk', 'cashier') NOT NULL DEFAULT 'cashier'";
    if ($conn->query($alter_sql) === FALSE) {
        die("Error adding role column: " . $conn->error);
    }
}

// Create default admin user if not exists
$default_username = "admin";
$default_password = password_hash("admin123", PASSWORD_DEFAULT);
$default_email = "admin@example.com";
$default_role = "admin";

// Check if admin user already exists
$check_admin = $conn->prepare("SELECT id FROM users WHERE username = ?");
$check_admin->bind_param("s", $default_username);
$check_admin->execute();
$result = $check_admin->get_result();

if ($result->num_rows == 0) {
    // Insert new admin user
    $stmt = $conn->prepare("INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $default_username, $default_password, $default_email, $default_role);
    $stmt->execute();
} else {
    // Update existing admin user to have admin role
    $update_admin = $conn->prepare("UPDATE users SET role = ? WHERE username = ?");
    $update_admin->bind_param("ss", $default_role, $default_username);
    $update_admin->execute();
}

?>
