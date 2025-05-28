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

W3B# Sales Transaction Integration - Implementation Summary

## Overview
Successfully integrated inventory transaction logging into the sales system. Now when sales are made through either the web interface or API, inventory transaction records are automatically created to maintain a complete audit trail.

## Changes Made

### 1. Modified `sales.php` (Web Interface)
- **Location**: Lines 147-172 (approximately)
- **Changes**: 
  - Added inventory transaction record creation after successful sales
  - Added table existence check to ensure robustness
  - Records transaction type as 'sale'
  - Captures product details, quantity, price, and user information

### 2. Modified `api/sales.php` (API Endpoint)
- **Location**: Lines 249-283 (approximately)
- **Changes**:
  - Added inventory transaction record creation in API sales endpoint
  - Added table existence check for database safety
  - Records API sales with appropriate user identification
  - Maintains transaction consistency with error handling

## Database Integration

### Inventory Transactions Table
The system now utilizes the existing `inventory_transactions` table with these fields for sales:
- `product_id`: Links to the products table
- `product_name`: Product name at time of sale
- `transaction_type`: Set to 'sale'
- `quantity`: Quantity sold (recorded as positive number)
- `unit_price`: Price per unit at time of sale
- `total_value`: Total transaction value
- `notes`: 'Sale transaction' or 'Sale transaction via API'
- `created_by`: Username or 'system'/'api_user'
- `transaction_date`: Automatic timestamp

## Features Implemented

### 1. Complete Audit Trail
- Every sale now creates an inventory transaction record
- Both web and API sales are tracked
- Transaction records include full context (user, date, amounts)

### 2. Database Safety
- Table existence checks ensure the system works even if tables are missing
- Automatic table creation with proper schema if needed
- Foreign key relationships maintained

### 3. User Tracking
- Web sales: Records actual logged-in username
- API sales: Records 'api_user' identifier
- System fallback for edge cases

### 4. Error Handling
- Transaction creation errors are handled gracefully
- API endpoint includes transaction creation in error handling

## Testing

### Test Files Created
1. `test_sales_integration.php` - Comprehensive system status check
2. `test_api_sales.php` - API endpoint testing with authentication

### Verification Steps
1. **Web Interface**: Create a sale through `sales.php`
2. **API**: Use POST request to `api/sales.php?action=add`
3. **View Results**: Check `inventory_transactions.php` for new records

## Benefits

### 1. Complete Inventory Tracking
- All inventory movements (deliveries and sales) are now tracked
- Easy to see product flow and identify discrepancies
- Historical data for analysis and reporting

### 2. Audit Compliance
- Full audit trail for all sales transactions
- User accountability for all inventory changes
- Timestamp tracking for all movements

### 3. Data Integrity
- Consistent recording across all sales channels
- Automatic transaction recording prevents missed entries
- Foreign key relationships ensure data consistency

## Integration with Existing System

### Inventory Transactions Page
The existing `inventory_transactions.php` page will now display:
- **Delivery transactions**: From supplier orders (existing)
- **Sales transactions**: From completed sales (new)
- **Summary data**: Updated to include sales information

### Delivery Integration
Sales transactions complement the existing delivery transaction system:
- Deliveries increase inventory (positive transactions)
- Sales decrease inventory (recorded as positive quantities but represent reductions)
- Complete picture of inventory flow in one location

## Usage Examples

### Web Interface Sale
1. Navigate to `sales.php`
2. Select product and quantity
3. Submit sale
4. System automatically:
   - Creates order record
   - Updates product quantity
   - **Creates inventory transaction record**

### API Sale
```bash
POST /api/sales.php?action=add
Authorization: Bearer {token}
Content-Type: application/json

{
    "product_id": 1,
    "quantity": 5,
    "sales_channel": "pos",
    "notes": "Point of sale transaction"
}
```

Response includes sale confirmation and inventory transaction creation.

## Maintenance Notes

### Future Enhancements
- Add return transaction support (transaction_type = 'return')
- Add adjustment transaction support (transaction_type = 'adjustment')
- Add batch processing for bulk sales
- Add transaction reversal capabilities

### Monitoring
- Monitor `inventory_transactions` table for all sales activity
- Use transaction data for sales reporting and analysis
- Verify transaction consistency with order records

This implementation ensures that all sales activities are properly tracked and audited, providing complete visibility into inventory movements and supporting business compliance requirements.

# Order System Updates Summary

## ✅ Completed Changes (May 27, 2025)

### 1. Status Options Simplified
- **BEFORE**: pending, ordered, shipped, delivered, cancelled
- **AFTER**: ordered, delivered, cancelled
- Updated both filter dropdown and status update modal

### 2. Button Text Changes
- **BEFORE**: "New Supplier Order"
- **AFTER**: "Resupply Products"

### 3. New Supplier Creation Feature
- Added "New Supplier" button next to supplier dropdown
- Opens modal form to add supplier with:
  - Supplier Name (required)
  - Email (required)
  - Phone Number (required)
- Automatically creates suppliers table if it doesn't exist
- Includes sample suppliers on first run
- Page refreshes after creation to update dropdown

### 4. New Product Creation Feature
- Added "New Product" button next to product dropdown
- Opens modal form to add product with:
  - Product Name (required)
  - Unit Price (required)
  - Alert Quantity (default: 10)
- Creates products with initial quantity of 0
- Page refreshes after creation to update dropdown

### 5. Database Enhancements
- Auto-creates suppliers table with proper structure
- Includes sample data insertion for suppliers
- Maintains existing product creation functionality

### 6. UI/UX Improvements
- Flexible layout for supplier/product selection
- Inline "New" buttons next to dropdowns
- Proper modal management for all forms
- Consistent styling across all modals

## Files Modified
- `order.php` - Main order management system
- Added new form handlers for supplier/product creation
- Enhanced modal system with multiple forms
- Updated JavaScript for modal management

## Features Ready for Use
1. ✅ Simplified order status workflow
2. ✅ Quick supplier addition during order creation
3. ✅ Quick product addition for new inventory items
4. ✅ Improved user experience with inline creation options
5. ✅ Auto-database setup for suppliers table

## Testing Checklist
- [ ] Test order creation with existing suppliers/products
- [ ] Test new supplier creation and immediate use
- [ ] Test new product creation and immediate use
- [ ] Test status updates (ordered → delivered → cancelled)
- [ ] Verify inventory updates when orders are delivered
- [ ] Test search and filter functionality

## Next Steps
- Test the complete workflow in browser
- Verify database table creation
- Confirm all modals open/close properly
- Validate form submissions work correctly


# User Activity Logging System - Implementation Complete! 🎉

## 📋 System Overview

The comprehensive User Activity Logging System has been successfully implemented and is now **FULLY OPERATIONAL**. This system tracks all user login/logout activities, calculates session durations, and provides powerful administrative tools for monitoring user behavior.

## ✅ Completed Features

### Core Logging Infrastructure
- ✅ **Database Table**: `user_activity_logs` with optimized indexes
- ✅ **UserActivityLogger Class**: Complete logging functionality
- ✅ **Login Integration**: Automatic activity logging on user login
- ✅ **Logout Integration**: Session duration tracking on logout
- ✅ **Error Handling**: Comprehensive error management and logging

### Administrative Dashboard
- ✅ **Statistics Dashboard**: Real-time activity statistics
- ✅ **Activity Logs Viewer**: Paginated log viewing with filters
- ✅ **Active Users Monitor**: Live tracking of currently active users
- ✅ **Data Export**: CSV and JSON export functionality
- ✅ **Cleanup Tools**: Automatic old log cleanup with confirmation

### User Interface
- ✅ **Navigation Integration**: Added to admin sidebar
- ✅ **Responsive Design**: Mobile-friendly interface
- ✅ **Interactive Filters**: Date range, user, and activity type filtering
- ✅ **Enhanced UX**: Modern cards, pagination, and visual indicators

## 🛠 Files Created/Modified

### New Files Created:
1. `config/activity_logger.php` - Core UserActivityLogger class
2. `user_activity_logs.php` - Admin dashboard for viewing logs
3. `export_activity_logs.php` - Export functionality (CSV/JSON)
4. `create_user_activity_logs.sql` - Database table creation script
5. `test_activity_logging.php` - System testing script
6. `comprehensive_test.php` - Detailed system verification
7. `test_complete_workflow.php` - Complete workflow testing
8. `setup_activity_logging.php` - Database setup script
9. `USER_ACTIVITY_LOGGING_SUMMARY.md` - Documentation

### Modified Files:
1. `login.php` - Added activity logging on successful login
2. `logout.php` - Added activity logging and session duration tracking
3. `templates/sidebar.php` - Added "Activity Logs" navigation link

## 🚀 Testing & Verification

### Test Pages Available:
- **Comprehensive Test**: `http://localhost/inventorysystem/Inventorysystem1-1/comprehensive_test.php`
- **Complete Workflow Test**: `http://localhost/inventorysystem/Inventorysystem1-1/test_complete_workflow.php`
- **Activity Logs Dashboard**: `http://localhost/inventorysystem/Inventorysystem1-1/user_activity_logs.php`

### System Verification Steps:
1. ✅ Database connection and table creation
2. ✅ UserActivityLogger class functionality
3. ✅ Login/logout integration testing
4. ✅ Statistics calculation accuracy
5. ✅ Export functionality (CSV/JSON)
6. ✅ Admin dashboard accessibility
7. ✅ Navigation integration
8. ✅ Error handling and security

## 📊 Database Schema

```sql
CREATE TABLE user_activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    username VARCHAR(50) NOT NULL,
    user_role VARCHAR(20) NOT NULL,
    activity_type ENUM('login', 'logout', 'session_timeout') NOT NULL,
    login_time TIMESTAMP NULL,
    logout_time TIMESTAMP NULL,
    session_duration INT NULL COMMENT 'Duration in seconds',
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_user_id (user_id),
    INDEX idx_activity_type (activity_type),
    INDEX idx_login_time (login_time),
    INDEX idx_created_at (created_at)
);
```

## 🔧 Configuration

### Database Configuration
- **Host**: localhost
- **Database**: inventory_db
- **Table**: user_activity_logs
- **Indexes**: Optimized for performance

### Security Features
- SQL injection prevention using prepared statements
- XSS protection with HTML escaping
- Session-based authentication
- IP address and user agent tracking
- Admin-only access to activity logs

## 📈 Statistics Tracked

The system automatically calculates and displays:
- **Total Logins**: All-time login count
- **Unique Users**: Number of different users who have logged in
- **Today's Logins**: Login count for current day
- **Active Users**: Currently logged-in users
- **Average Session Duration**: Mean time spent per session
- **Activity by Time**: Hourly/daily activity patterns

## 💾 Export Capabilities

### CSV Export Features:
- Complete activity log data
- Proper CSV formatting with headers
- UTF-8 encoding support
- Filterable by date range

### JSON Export Features:
- Structured data with metadata
- Timestamp information
- Export statistics included
- API-ready format

## 🎯 Usage Instructions

### For Administrators:
1. Navigate to **Admin > Activity Logs** in the sidebar
2. View real-time statistics and active users
3. Filter logs by date, user, or activity type
4. Export data using CSV or JSON format
5. Use cleanup tools to manage old logs

### For System Monitoring:
1. Monitor login patterns and user behavior
2. Track session durations and activity peaks
3. Identify security concerns or unusual activity
4. Generate reports for compliance or analysis

## 🚨 System Status

**🟢 FULLY OPERATIONAL**

All components are working correctly:
- ✅ Database connectivity
- ✅ Logging functionality
- ✅ Admin dashboard
- ✅ Export features
- ✅ Navigation integration
- ✅ Error handling
- ✅ Security measures

## 🎉 Implementation Summary

The User Activity Logging System is **COMPLETE** and ready for production use. The system provides:

1. **Comprehensive Tracking** - Every login/logout is logged with detailed information
2. **Powerful Analytics** - Real-time statistics and reporting capabilities
3. **Administrative Tools** - Easy-to-use dashboard for system administrators
4. **Data Export** - Multiple format support for data analysis
5. **Security & Performance** - Optimized queries and secure implementation
6. **User Experience** - Modern, responsive interface design

**🎊 The system is now live and actively monitoring user activities!**

---

*Implementation completed on May 27, 2025*
*All features tested and verified*
*Ready for production deployment*
