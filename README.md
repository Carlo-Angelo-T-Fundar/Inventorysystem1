# Inventory Management System API

## Overview

This RESTful API provides programmatic access to the Inventory Management System. It allows external applications to manage users, inventory, orders, and sales through standardized HTTP endpoints.

## Base URL

```
http://your-server/inventorysystem/Inventorysystem1-1/api/
```

## Authentication

The API uses token-based authentication. To obtain a token:

1. Make a POST request to `/api/auth.php?action=login` with your username and password
2. The response will include a token that should be included in the Authorization header of all subsequent requests

Example:

```
Authorization: Bearer your_token_here
```

Tokens expire after 24 hours. You can invalidate a token by calling the logout endpoint.

## Error Handling

The API returns appropriate HTTP status codes along with JSON error responses. Error responses have the following format:

```json
{
  "status": "error",
  "message": "Error message describing what went wrong",
  "error_code": "UNIQUE_ERROR_CODE"
}
```

Common HTTP status codes:

- `200 OK` - Request succeeded
- `400 Bad Request` - Invalid request parameters
- `401 Unauthorized` - Authentication required or failed
- `403 Forbidden` - Insufficient permissions
- `404 Not Found` - Resource not found
- `405 Method Not Allowed` - HTTP method not supported for this endpoint
- `500 Internal Server Error` - Server-side error

## Available Endpoints

### Authentication

- `POST /api/auth.php?action=login` - Authenticate user and get access token
- `GET /api/auth.php?action=logout` - Invalidate current access token

### Users (Admin only)

- `GET /api/users.php?action=list` - Get all users
- `GET /api/users.php?action=get&id={user_id}` - Get a specific user by ID
- `POST /api/users.php?action=create` - Create a new user
- `POST /api/users.php?action=update` - Update an existing user
- `DELETE /api/users.php?action=delete&id={user_id}` - Delete a user

### Inventory

- `GET /api/inventory.php?action=list` - Get all products with optional filtering
- `GET /api/inventory.php?action=get&id={product_id}` - Get a specific product by ID
- `POST /api/inventory.php?action=create` - Create a new product
- `POST /api/inventory.php?action=update` - Update an existing product
- `DELETE /api/inventory.php?action=delete&id={product_id}` - Delete a product

### Orders

- `GET /api/orders.php?action=list` - Get all orders with optional filtering
- `GET /api/orders.php?action=get&id={order_id}` - Get a specific order by ID
- `POST /api/orders.php?action=create` - Create a new order
- `POST /api/orders.php?action=update_status` - Update the status of an existing order

### Sales

- `GET /api/sales.php?action=summary` - Get sales summary statistics
- `GET /api/sales.php?action=top_products&limit={limit}` - Get top selling products
- `POST /api/sales.php?action=add` - Add a new sale (completes an order automatically)
- `GET /api/sales.php?action=by_date&start_date={YYYY-MM-DD}&end_date={YYYY-MM-DD}` - Get sales data by date range

## Role-Based Permissions

The API enforces role-based access control:

- **Admin**: Full access to all endpoints
- **Store Clerk**: Access to inventory management and order management
- **Supplier**: Access to inventory management (view/update)
- **Cashier**: Access to sales operations and order creation

## Testing with Postman

A Postman collection file (`inventory_api_postman_collection.json`) is included in the project root. To use it:

1. Import the collection into Postman
2. Set the `base_url` variable to your server URL
3. Make a login request to get a token
4. Update the `token` variable with the token you received
5. Test the other endpoints

## Documentation

For full API documentation with examples, visit:

```
http://your-server/inventorysystem/Inventorysystem1-1/api/docs.php
```

## Support

For issues or questions about the API, please contact the system administrator.


# Inventorysystem1# User Role System Implementation

## Overview
The inventory management system has been updated to include a comprehensive user role system that controls access to different features based on user responsibilities.

## User Roles

### 1. Admin
- **Full Access** - Overall operations and system management
- **Permissions:**
  - View and manage all modules (Dashboard, Inventory, Orders, Sales, Charts)
  - Manage users (create, view user accounts)
  - Access to all system features
  - Can perform all CRUD operations

### 2. Supplier
- **Resupply Management** - Focused on inventory restocking
- **Permissions:**
  - Dashboard access
  - Inventory management (view, add, update stock levels)
  - Charts and reports
  - **Cannot access:** Orders, Sales, User Management

### 3. Store Clerk
- **Product Availability Control** - Manages inventory and order fulfillment
- **Permissions:**
  - Dashboard access
  - Inventory management (full access)
  - Order management (view, process orders)
  - Charts and reports
  - **Cannot access:** Sales, User Management

### 4. Cashier
- **Sales Operations** - Handles sales and customer transactions
- **Permissions:**
  - Dashboard access
  - Order management (create, view, process orders)
  - Sales management (view, record sales)
  - Charts and reports
  - **Cannot access:** Inventory Management, User Management

## Database Changes

### Users Table Schema
```sql
ALTER TABLE users ADD COLUMN role ENUM('admin', 'supplier', 'store_clerk', 'cashier') NOT NULL DEFAULT 'cashier';
```

### Default Admin Account
- **Username:** admin
- **Password:** admin123
- **Role:** admin
- **Email:** admin@example.com

## File Changes Made

### 1. Database Configuration (`config/db.php`)
- Added role column to users table
- Updated default admin user creation with role
- Added migration logic for existing installations

### 2. Authentication System (`config/auth.php`)
- Added `checkUserRole()` function
- Added `getCurrentUserRole()` function  
- Added `requireRole()` function for access control

### 3. User Management (`admin/manage_users.php`)
- Updated to use role-based admin check
- Added role selection dropdown in user creation form
- Updated user listing to display roles
- Enhanced form validation

### 4. Login System (`login.php`)
- Updated to store user role in session
- Enhanced user authentication query

### 5. Navigation (`templates/sidebar.php`)
- Role-based navigation menu
- Dynamic role display in header
- Conditional menu items based on user permissions

### 6. Page Access Controls
- **Inventory.php:** Admin, Store Clerk, Supplier access
- **Order.php:** Admin, Cashier, Store Clerk access
- **Sales.php:** Admin, Cashier access

### 7. Migration Script (`config/migrate_user_roles.php`)
- Automated database migration
- Sets default roles for existing users
- Provides migration status and user role summary

## Usage Instructions

### For Administrators
1. Login with admin credentials
2. Navigate to "Manage Users" to create new user accounts
3. Select appropriate roles based on job responsibilities
4. Monitor system access through role-based permissions

### For New Installations
- The system will automatically create the admin user and role structure
- Default admin credentials: admin/admin123

### For Existing Installations
- Run the migration script: `php config/migrate_user_roles.php`
- Existing users will be assigned 'cashier' role by default
- Admin user will be automatically updated to 'admin' role

## Security Features
- Session-based authentication with role validation
- Function-level access control
- Redirect unauthorized users to appropriate pages
- Role validation on every protected page access

## Role Hierarchy
```
Admin (Full Access)
├── Supplier (Inventory + Dashboard)
├── Store Clerk (Inventory + Orders + Dashboard)
└── Cashier (Orders + Sales + Dashboard)
```

## Future Enhancements
- Role-based dashboard widgets
- Audit logging for role-based actions
- More granular permissions within roles
- Role-based reporting restrictions
