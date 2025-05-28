<?php
// API Router/Index file
require_once 'config.php';

// Default API info response
$api_version = '1.0';
$api_base_url = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$api_base_url .= $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);

// Define available endpoints
$available_endpoints = [
    'auth' => [
        'login' => [
            'method' => 'POST',
            'url' => $api_base_url . '/auth.php?action=login',
            'description' => 'Authenticate user and get access token',
            'requires_auth' => false
        ],
        'logout' => [
            'method' => 'GET',
            'url' => $api_base_url . '/auth.php?action=logout',
            'description' => 'Invalidate current access token',
            'requires_auth' => true
        ]
    ],
    'users' => [
        'list' => [
            'method' => 'GET',
            'url' => $api_base_url . '/users.php?action=list',
            'description' => 'Get all users (admin only)',
            'requires_auth' => true
        ],
        'get' => [
            'method' => 'GET',
            'url' => $api_base_url . '/users.php?action=get&id={user_id}',
            'description' => 'Get a specific user by ID (admin only)',
            'requires_auth' => true
        ],
        'create' => [
            'method' => 'POST',
            'url' => $api_base_url . '/users.php?action=create',
            'description' => 'Create a new user (admin only)',
            'requires_auth' => true
        ],
        'update' => [
            'method' => 'POST',
            'url' => $api_base_url . '/users.php?action=update',
            'description' => 'Update an existing user (admin only)',
            'requires_auth' => true
        ],
        'delete' => [
            'method' => 'DELETE',
            'url' => $api_base_url . '/users.php?action=delete&id={user_id}',
            'description' => 'Delete a user (admin only)',
            'requires_auth' => true
        ]
    ],
    'inventory' => [
        'list' => [
            'method' => 'GET',
            'url' => $api_base_url . '/inventory.php?action=list',
            'description' => 'Get all products with optional filtering',
            'requires_auth' => true
        ],
        'get' => [
            'method' => 'GET',
            'url' => $api_base_url . '/inventory.php?action=get&id={product_id}',
            'description' => 'Get a specific product by ID',
            'requires_auth' => true
        ],
        'create' => [
            'method' => 'POST',
            'url' => $api_base_url . '/inventory.php?action=create',
            'description' => 'Create a new product',
            'requires_auth' => true
        ],
        'update' => [
            'method' => 'POST',
            'url' => $api_base_url . '/inventory.php?action=update',
            'description' => 'Update an existing product',
            'requires_auth' => true
        ],
        'delete' => [
            'method' => 'DELETE',
            'url' => $api_base_url . '/inventory.php?action=delete&id={product_id}',
            'description' => 'Delete a product',
            'requires_auth' => true
        ]
    ],
    'orders' => [
        'list' => [
            'method' => 'GET',
            'url' => $api_base_url . '/orders.php?action=list',
            'description' => 'Get all orders with optional filtering',
            'requires_auth' => true
        ],
        'get' => [
            'method' => 'GET',
            'url' => $api_base_url . '/orders.php?action=get&id={order_id}',
            'description' => 'Get a specific order by ID',
            'requires_auth' => true
        ],
        'create' => [
            'method' => 'POST',
            'url' => $api_base_url . '/orders.php?action=create',
            'description' => 'Create a new order',
            'requires_auth' => true
        ],
        'update_status' => [
            'method' => 'POST',
            'url' => $api_base_url . '/orders.php?action=update_status',
            'description' => 'Update the status of an existing order',
            'requires_auth' => true
        ]
    ],
    'sales' => [
        'summary' => [
            'method' => 'GET',
            'url' => $api_base_url . '/sales.php?action=summary',
            'description' => 'Get sales summary statistics',
            'requires_auth' => true
        ],
        'top_products' => [
            'method' => 'GET',
            'url' => $api_base_url . '/sales.php?action=top_products&limit={limit}',
            'description' => 'Get top selling products',
            'requires_auth' => true
        ],
        'add' => [
            'method' => 'POST',
            'url' => $api_base_url . '/sales.php?action=add',
            'description' => 'Add a new sale (completes an order automatically)',
            'requires_auth' => true
        ],
        'by_date' => [
            'method' => 'GET',
            'url' => $api_base_url . '/sales.php?action=by_date&start_date={YYYY-MM-DD}&end_date={YYYY-MM-DD}',
            'description' => 'Get sales data by date range',
            'requires_auth' => true
        ]
    ]
];

// Return API information
$api_info = [
    'status' => 'success',
    'api' => [
        'name' => 'Inventory Management System API',
        'version' => $api_version,
        'base_url' => $api_base_url,
        'documentation' => $api_base_url . '/docs',
        'auth_type' => 'Bearer Token'
    ],
    'endpoints' => $available_endpoints
];

// Get JWT token if it exists
$token = get_api_token();
$authenticated = false;

if ($token) {
    $user_id = verify_api_token($token, $conn);
    if ($user_id) {
        $authenticated = true;
        
        // Get user role
        $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        $api_info['user'] = [
            'id' => $user_id,
            'role' => $user['role'],
            'permissions' => get_role_permissions($user['role'])
        ];
    }
}

$api_info['authenticated'] = $authenticated;

// Return API info
send_response($api_info);

// Helper function to get permissions by role
function get_role_permissions($role) {
    $permissions = [
        'admin' => [
            'users' => ['read', 'create', 'update', 'delete'],
            'inventory' => ['read', 'create', 'update', 'delete'],
            'orders' => ['read', 'create', 'update', 'delete'],
            'sales' => ['read', 'create', 'update', 'delete']
        ],
        'supplier' => [
            'inventory' => ['read', 'update'],
            'orders' => ['read']
        ],
        'store_clerk' => [
            'inventory' => ['read', 'update'],
            'orders' => ['read', 'create', 'update']
        ],
        'cashier' => [
            'inventory' => ['read'],
            'orders' => ['read', 'create'],
            'sales' => ['read', 'create']
        ]
    ];
    
    return isset($permissions[$role]) ? $permissions[$role] : [];
}
