<?php
// API Documentation
header('Content-Type: text/html');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">    <title>Inventory Management System API Documentation</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>        body {
            font-family: 'Inter', sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f9f9f9;
        }
        
        h1, h2, h3, h4 {
            color: #1a56db;
            margin-top: 1.5em;
            margin-bottom: 0.5em;
        }
        
        h1 {
            text-align: center;
            font-size: 2.5rem;
            margin-bottom: 1em;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 0.5em;
        }
        
        h2 {
            font-size: 1.8rem;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 0.3em;
        }
        
        h3 {
            font-size: 1.3rem;
        }
        
        p {
            margin-bottom: 1em;
        }
        
        code {
            font-family: 'Courier New', Courier, monospace;
            background-color: #f0f0f0;
            padding: 0.2em 0.4em;
            border-radius: 3px;
            font-size: 0.9em;
        }
        
        pre {
            background-color: #f0f0f0;
            padding: 1em;
            border-radius: 5px;
            overflow-x: auto;
            margin: 1em 0;
            font-family: 'Courier New', Courier, monospace;
        }
        
        .endpoint {
            background-color: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 1em;
            margin-bottom: 1.5em;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .method {
            display: inline-block;
            padding: 0.3em 0.6em;
            border-radius: 4px;
            color: white;
            font-weight: bold;
            font-size: 0.8em;
            margin-right: 0.5em;
        }
        
        .get {
            background-color: #22c55e;
        }
        
        .post {
            background-color: #3b82f6;
        }
        
        .put {
            background-color: #f59e0b;
        }
        
        .delete {
            background-color: #ef4444;
        }
        
        .endpoint-header {
            display: flex;
            align-items: center;
            margin-bottom: 0.5em;
        }
        
        .endpoint-url {
            font-family: 'Courier New', Courier, monospace;
            font-weight: 500;
        }
        
        .params-table {
            width: 100%;
            border-collapse: collapse;
            margin: 1em 0;
        }
        
        .params-table th, .params-table td {
            padding: 0.75em;
            border: 1px solid #e5e7eb;
            text-align: left;
        }
        
        .params-table th {
            background-color: #f3f4f6;
            font-weight: 600;
        }
        
        .section-separator {
            height: 1px;
            background-color: #e5e7eb;
            margin: 2em 0;
        }
        
        .auth-section {
            background-color: #f3f4f6;
            border-left: 4px solid #1a56db;
            padding: 1em;
            margin: 1.5em 0;
        }
        
        .required {
            color: #ef4444;
            font-weight: 500;
        }
        
        .optional {
            color: #6b7280;
            font-style: italic;
        }
        
        .note {
            background-color: #fffbeb;
            border-left: 4px solid #f59e0b;
            padding: 1em;
            margin: 1em 0;
        }
        
        .response-example {
            background-color: #f8fafc;
            border-left: 4px solid #22c55e;
            padding: 1em;
            margin: 1em 0;
        }
        
        .error-example {
            background-color: #fef2f2;
            border-left: 4px solid #ef4444;
            padding: 1em;
            margin: 1em 0;
        }
        
        .toc {
            background-color: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 1em 2em;
            margin: 1em 0 2em;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .toc-title {
            font-weight: 600;
            font-size: 1.2rem;
            margin-bottom: 0.5em;
        }
        
        .toc ul {
            list-style-type: none;
            padding-left: 0;
        }
        
        .toc li {
            margin-bottom: 0.5em;
        }
        
        .toc a {
            color: #1a56db;
            text-decoration: none;
        }
        
        .toc a:hover {
            text-decoration: underline;
        }
        
        .version-badge {
            display: inline-block;
            background-color: #1a56db;
            color: white;
            padding: 0.2em 0.6em;
            border-radius: 9999px;
            font-size: 0.7em;
            vertical-align: middle;
            margin-left: 0.5em;
        }
    </style>
</head>
<body>
    <h1>Inventory Management System API Documentation <span class="version-badge">v1.0</span></h1>
    
    <div class="toc">
        <div class="toc-title">Table of Contents</div>
        <ul>
            <li><a href="#introduction">Introduction</a></li>
            <li><a href="#authentication">Authentication</a></li>
            <li><a href="#error-handling">Error Handling</a></li>
            <li><a href="#endpoints">API Endpoints</a>
                <ul>
                    <li><a href="#auth-endpoints">Authentication</a></li>
                    <li><a href="#users-endpoints">Users</a></li>
                    <li><a href="#inventory-endpoints">Inventory</a></li>
                    <li><a href="#orders-endpoints">Orders</a></li>
                    <li><a href="#sales-endpoints">Sales</a></li>
                </ul>
            </li>
        </ul>
    </div>
    
    <h2 id="introduction">Introduction</h2>
    <p>
        Welcome to the Inventory Management System API documentation. This API provides a set of endpoints to interact with the inventory management system, allowing you to manage users, inventory, orders, and sales.
    </p>
    <p>
        The API uses RESTful principles and returns responses in JSON format. All endpoints require authentication except for the login endpoint.
    </p>
    
    <h2 id="authentication">Authentication</h2>
    <div class="auth-section">
        <p>
            The API uses token-based authentication. To authenticate, you need to:
        </p>
        <ol>
            <li>Make a POST request to <code>/api/auth.php?action=login</code> with your credentials</li>
            <li>Include the returned token in the Authorization header of all subsequent requests</li>
        </ol>
        <p>
            Example Authorization header:
        </p>
        <pre>Authorization: Bearer your_token_here</pre>
        <p>
            Tokens expire after 24 hours. You can invalidate a token by calling the logout endpoint.
        </p>
    </div>
    
    <h2 id="error-handling">Error Handling</h2>
    <p>
        The API returns appropriate HTTP status codes along with JSON error responses. Error responses have the following format:
    </p>
    <div class="error-example">
        <pre>{
  "status": "error",
  "message": "Error message describing what went wrong",
  "error_code": "UNIQUE_ERROR_CODE"
}</pre>
    </div>
    <p>
        Common HTTP status codes:
    </p>
    <ul>
        <li><code>200 OK</code> - Request succeeded</li>
        <li><code>400 Bad Request</code> - Invalid request parameters</li>
        <li><code>401 Unauthorized</code> - Authentication required or failed</li>
        <li><code>403 Forbidden</code> - Insufficient permissions</li>
        <li><code>404 Not Found</code> - Resource not found</li>
        <li><code>405 Method Not Allowed</code> - HTTP method not supported for this endpoint</li>
        <li><code>500 Internal Server Error</code> - Server-side error</li>
    </ul>
    
    <div class="section-separator"></div>
    
    <h2 id="endpoints">API Endpoints</h2>
    
    <h3 id="auth-endpoints">Authentication Endpoints</h3>
    
    <div class="endpoint">
        <div class="endpoint-header">
            <span class="method post">POST</span>
            <span class="endpoint-url">/api/auth.php?action=login</span>
        </div>
        <p>Authenticate user and get access token</p>
        
        <h4>Request Body</h4>
        <div class="params-table">
            <table>
                <thead>
                    <tr>
                        <th>Parameter</th>
                        <th>Type</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><span class="required">username</span></td>
                        <td>string</td>
                        <td>User's username</td>
                    </tr>
                    <tr>
                        <td><span class="required">password</span></td>
                        <td>string</td>
                        <td>User's password</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <h4>Response</h4>
        <div class="response-example">
            <pre>{
  "status": "success",
  "message": "Login successful",
  "data": {
    "token": "your_jwt_token_here",
    "user": {
      "id": 1,
      "username": "admin",
      "role": "admin"
    }
  }
}</pre>
        </div>
    </div>
    
    <div class="endpoint">
        <div class="endpoint-header">
            <span class="method get">GET</span>
            <span class="endpoint-url">/api/auth.php?action=logout</span>
        </div>
        <p>Invalidate the current access token</p>
        
        <h4>Headers</h4>
        <div class="params-table">
            <table>
                <thead>
                    <tr>
                        <th>Parameter</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><span class="required">Authorization</span></td>
                        <td>Bearer {token}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <h4>Response</h4>
        <div class="response-example">
            <pre>{
  "status": "success",
  "message": "Logout successful"
}</pre>
        </div>
    </div>
    
    <h3 id="users-endpoints">Users Endpoints</h3>
    
    <div class="note">
        <p><strong>Note:</strong> All User endpoints require admin privileges.</p>
    </div>
    
    <div class="endpoint">
        <div class="endpoint-header">
            <span class="method get">GET</span>
            <span class="endpoint-url">/api/users.php?action=list</span>
        </div>
        <p>Get a list of all users</p>
        
        <h4>Headers</h4>
        <div class="params-table">
            <table>
                <thead>
                    <tr>
                        <th>Parameter</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><span class="required">Authorization</span></td>
                        <td>Bearer {token}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <h4>Response</h4>
        <div class="response-example">
            <pre>{
  "status": "success",
  "data": {
    "users": [
      {
        "id": 1,
        "username": "admin",
        "email": "admin@example.com",
        "role": "admin",
        "created_at": "2023-04-15 10:30:00"
      },
      {
        "id": 2,
        "username": "user1",
        "email": "user1@example.com",
        "role": "cashier",
        "created_at": "2023-04-16 14:20:00"
      }
    ]
  }
}</pre>
        </div>
    </div>
    
    <!-- More user endpoints would be documented here -->
    
    <h3 id="inventory-endpoints">Inventory Endpoints</h3>
    
    <div class="endpoint">
        <div class="endpoint-header">
            <span class="method get">GET</span>
            <span class="endpoint-url">/api/inventory.php?action=list</span>
        </div>
        <p>Get a list of all products</p>
        
        <h4>Headers</h4>
        <div class="params-table">
            <table>
                <thead>
                    <tr>
                        <th>Parameter</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><span class="required">Authorization</span></td>
                        <td>Bearer {token}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <h4>Query Parameters</h4>
        <div class="params-table">
            <table>
                <thead>
                    <tr>
                        <th>Parameter</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><span class="optional">search</span></td>
                        <td>Search term to filter products by name</td>
                    </tr>
                    <tr>
                        <td><span class="optional">status</span></td>
                        <td>Filter by status (in_stock, low_stock, out_of_stock)</td>
                    </tr>
                    <tr>
                        <td><span class="optional">sort</span></td>
                        <td>Sort field (name, price, quantity)</td>
                    </tr>
                    <tr>
                        <td><span class="optional">order</span></td>
                        <td>Sort order (asc, desc)</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <h4>Response</h4>
        <div class="response-example">
            <pre>{
  "status": "success",
  "data": {
    "products": [
      {
        "id": 1,
        "name": "Product 1",
        "quantity": 15,
        "alert_quantity": 5,
        "price": 29.99,
        "status": "in_stock",
        "created_at": "2023-04-15 10:30:00",
        "updated_at": "2023-04-15 10:30:00"
      },
      {
        "id": 2,
        "name": "Product 2",
        "quantity": 3,
        "alert_quantity": 5,
        "price": 19.99,
        "status": "low_stock",
        "created_at": "2023-04-16 14:20:00",
        "updated_at": "2023-04-16 14:20:00"
      }
    ]
  }
}</pre>
        </div>
    </div>
    
    <!-- More inventory endpoints would be documented here -->
    
    <h3 id="orders-endpoints">Orders Endpoints</h3>
    
    <div class="endpoint">
        <div class="endpoint-header">
            <span class="method get">GET</span>
            <span class="endpoint-url">/api/orders.php?action=list</span>
        </div>
        <p>Get a list of all orders</p>
        
        <h4>Headers</h4>
        <div class="params-table">
            <table>
                <thead>
                    <tr>
                        <th>Parameter</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><span class="required">Authorization</span></td>
                        <td>Bearer {token}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <h4>Query Parameters</h4>
        <div class="params-table">
            <table>
                <thead>
                    <tr>
                        <th>Parameter</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><span class="optional">status</span></td>
                        <td>Filter by status (pending, processing, completed, cancelled)</td>
                    </tr>
                    <tr>
                        <td><span class="optional">date_from</span></td>
                        <td>Start date filter (YYYY-MM-DD)</td>
                    </tr>
                    <tr>
                        <td><span class="optional">date_to</span></td>
                        <td>End date filter (YYYY-MM-DD)</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <h4>Response</h4>
        <div class="response-example">
            <pre>{
  "status": "success",
  "data": {
    "orders": [
      {
        "id": 1,
        "total_amount": 149.99,
        "status": "completed",
        "sales_channel": "store",
        "destination": "Lalitpur",
        "created_at": "2023-04-15 10:30:00",
        "updated_at": "2023-04-15 10:30:00",
        "items": [
          {
            "id": 1,
            "product_id": 1,
            "product_name": "Product 1",
            "quantity": 5,
            "price": 29.99,
            "subtotal": 149.95
          }
        ]
      }
    ]
  }
}</pre>
        </div>
    </div>
    
    <!-- More order endpoints would be documented here -->
    
    <h3 id="sales-endpoints">Sales Endpoints</h3>
    
    <div class="endpoint">
        <div class="endpoint-header">
            <span class="method get">GET</span>
            <span class="endpoint-url">/api/sales.php?action=summary</span>
        </div>
        <p>Get sales summary statistics</p>
        
        <h4>Headers</h4>
        <div class="params-table">
            <table>
                <thead>
                    <tr>
                        <th>Parameter</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><span class="required">Authorization</span></td>
                        <td>Bearer {token}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <h4>Response</h4>
        <div class="response-example">
            <pre>{
  "status": "success",
  "data": {
    "summary": {
      "total_sales": 120,
      "total_revenue": 5499.85,
      "average_sale": 45.83,
      "highest_sale": 299.99,
      "active_days": 30
    },
    "monthly_trends": [
      {
        "month": "2023-01",
        "month_name": "Jan 2023",
        "orders": 25,
        "revenue": 1245.75
      },
      {
        "month": "2023-02",
        "month_name": "Feb 2023",
        "orders": 30,
        "revenue": 1399.50
      }
    ]
  }
}</pre>
        </div>
    </div>
    
    <div class="endpoint">
        <div class="endpoint-header">
            <span class="method get">GET</span>
            <span class="endpoint-url">/api/sales.php?action=top_products</span>
        </div>
        <p>Get top selling products</p>
        
        <h4>Headers</h4>
        <div class="params-table">
            <table>
                <thead>
                    <tr>
                        <th>Parameter</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><span class="required">Authorization</span></td>
                        <td>Bearer {token}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <h4>Query Parameters</h4>
        <div class="params-table">
            <table>
                <thead>
                    <tr>
                        <th>Parameter</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><span class="optional">limit</span></td>
                        <td>Number of products to return (default: 10, max: 50)</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <h4>Response</h4>
        <div class="response-example">
            <pre>{
  "status": "success",
  "data": {
    "top_products": [
      {
        "id": 1,
        "name": "Product 1",
        "total_sold": 50,
        "total_revenue": 1499.50,
        "current_stock": 15,
        "current_price": 29.99,
        "profit_margin": 0.00
      },
      {
        "id": 2,
        "name": "Product 2",
        "total_sold": 45,
        "total_revenue": 899.55,
        "current_stock": 5,
        "current_price": 19.99,
        "profit_margin": 0.00
      }
    ],
    "count": 2
  }
}</pre>
        </div>
    </div>
    
    <div class="endpoint">
        <div class="endpoint-header">
            <span class="method post">POST</span>
            <span class="endpoint-url">/api/sales.php?action=add</span>
        </div>
        <p>Add a new sale (completes an order automatically)</p>
        
        <h4>Headers</h4>
        <div class="params-table">
            <table>
                <thead>
                    <tr>
                        <th>Parameter</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><span class="required">Authorization</span></td>
                        <td>Bearer {token}</td>
                    </tr>
                    <tr>
                        <td><span class="required">Content-Type</span></td>
                        <td>application/json</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <h4>Request Body</h4>
        <div class="params-table">
            <table>
                <thead>
                    <tr>
                        <th>Parameter</th>
                        <th>Type</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><span class="required">product_id</span></td>
                        <td>integer</td>
                        <td>ID of the product to sell</td>
                    </tr>
                    <tr>
                        <td><span class="required">quantity</span></td>
                        <td>integer</td>
                        <td>Quantity to sell</td>
                    </tr>
                    <tr>
                        <td><span class="optional">sales_channel</span></td>
                        <td>string</td>
                        <td>Sales channel (default: "api")</td>
                    </tr>
                    <tr>
                        <td><span class="optional">destination</span></td>
                        <td>string</td>
                        <td>Destination or customer location</td>
                    </tr>
                    <tr>
                        <td><span class="optional">notes</span></td>
                        <td>string</td>
                        <td>Additional notes about the sale</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <h4>Response</h4>
        <div class="response-example">
            <pre>{
  "status": "success",
  "message": "Sale added successfully",
  "data": {
    "order_id": 6,
    "product": "Product 1",
    "quantity": 2,
    "total_amount": 59.98,
    "remaining_stock": 13
  }
}</pre>
        </div>
    </div>
    
    <div class="endpoint">
        <div class="endpoint-header">
            <span class="method get">GET</span>
            <span class="endpoint-url">/api/sales.php?action=by_date</span>
        </div>
        <p>Get sales data by date range</p>
        
        <h4>Headers</h4>
        <div class="params-table">
            <table>
                <thead>
                    <tr>
                        <th>Parameter</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><span class="required">Authorization</span></td>
                        <td>Bearer {token}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <h4>Query Parameters</h4>
        <div class="params-table">
            <table>
                <thead>
                    <tr>
                        <th>Parameter</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><span class="optional">start_date</span></td>
                        <td>Start date in YYYY-MM-DD format (default: 30 days ago)</td>
                    </tr>
                    <tr>
                        <td><span class="optional">end_date</span></td>
                        <td>End date in YYYY-MM-DD format (default: today)</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <h4>Response</h4>
        <div class="response-example">
            <pre>{
  "status": "success",
  "data": {
    "period": {
      "start_date": "2023-03-15",
      "end_date": "2023-04-15",
      "total_days": 32,
      "total_orders": 120,
      "total_revenue": 5499.85,
      "average_daily_revenue": 171.87
    },
    "daily_sales": [
      {
        "date": "2023-03-15",
        "orders": 5,
        "revenue": 249.95
      },
      {
        "date": "2023-03-16",
        "orders": 3,
        "revenue": 149.97
      }
    ]
  }
}</pre>
        </div>
    </div>
    
    <div class="section-separator"></div>
    
    <p style="text-align: center; color: #6b7280; margin-top: 2em;">
        &copy; <?php echo date('Y'); ?> Inventory Management System API Documentation
    </p>
</body>
</html>
