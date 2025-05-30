# Inventory Management System

A comprehensive inventory management solution built for small to medium businesses. This system helps you track products, manage orders, process sales, and keep an eye on your stock levels.

## What's Inside

This is a complete web-based inventory system that includes:
- Product management with low-stock alerts
- Supplier order management with approval workflows
- Sales tracking and reporting
- User management with role-based permissions
- Real-time activity logging
- REST API for integrations

## Getting Started

The system runs on any web server with PHP and MySQL. Just drop it in your web directory and access it through your browser:

```
http://your-server/inventorysystem/Inventorysystem1-1/
```

## API Access

If you need to connect other applications, we've got a REST API. To get started:

1. Log in through the API: `POST /api/auth.php?action=login`
2. Use the token you get back in your requests: `Authorization: Bearer your_token`
3. Tokens are good for 24 hours

Your API base URL will be:
```
http://your-server/inventorysystem/Inventorysystem1-1/api/
```

## When Things Go Wrong

If something doesn't work, the API will tell you what happened with standard HTTP codes and clear error messages:

```json
{
  "error": true,
  "message": "Here's what went wrong",
  "code": "ERROR_CODE"
}
```

## Recent Updates
- **May 27, 2025**: Updated access controls - Cashiers can only see sales, Store Clerks handle deliveries, and summary reports are admin-only

Quick reference for HTTP status codes:
- `200 OK` - Everything worked
- `400 Bad Request` - Check your request data
- `401 Unauthorized` - You need to log in
- `403 Forbidden` - You don't have permission for that
- `404 Not Found` - That doesn't exist
- `405 Method Not Allowed` - Wrong HTTP method
- `500 Internal Server Error` - Something broke on our end

## What You Can Do

### Authentication
- `POST /api/auth.php?action=login` - Log in and get your access token
- `GET /api/auth.php?action=logout` - Log out and clear your token

### User Management (Admins Only)
- `GET /api/users.php?action=list` - See all users
- `GET /api/users.php?action=get&id={user_id}` - Get details for one user
- `POST /api/users.php?action=create` - Add a new user
- `POST /api/users.php?action=update` - Update existing user
- `DELETE /api/users.php?action=delete&id={user_id}` - Remove a user

### Product Management
- `GET /api/inventory.php?action=list` - See all your products (with filters)
- `GET /api/inventory.php?action=get&id={product_id}` - Get details for one product
- `POST /api/inventory.php?action=create` - Add a new product
- `POST /api/inventory.php?action=update` - Update product info
- `DELETE /api/inventory.php?action=delete&id={product_id}` - Remove a product

### Order Management
- `GET /api/orders.php?action=list` - See all orders (with filters)
- `GET /api/orders.php?action=get&id={order_id}` - Get details for one order
- `POST /api/orders.php?action=create` - Create a new order
- `POST /api/orders.php?action=update_status` - Update order status

### Sales & Reports
- `GET /api/sales.php?action=summary` - Get sales overview
- `GET /api/sales.php?action=top_products&limit={limit}` - See your bestsellers
- `POST /api/sales.php?action=add` - Record a sale
- `GET /api/sales.php?action=by_date&start_date={YYYY-MM-DD}&end_date={YYYY-MM-DD}` - Sales by date range

## Who Can Do What

We've set up different permission levels based on your role:

- **Admin**: Can do everything
- **Store Clerk**: Manages inventory and processes orders
- **Supplier**: Can view and update inventory
- **Cashier**: Handles sales and creates orders

## Testing the API

We've included a Postman collection (`inventory_api_postman_collection.json`) to make testing easier:

1. Import it into Postman
2. Set your `base_url` variable
3. Log in to get a token
4. Update the `token` variable
5. Start testing!

## Need Help?

Check out our interactive documentation:
```
http://your-server/inventorysystem/Inventorysystem1-1/api/docs.php
```

For questions or issues, contact your system administrator.

---

# User Roles & Permissions

We've built a role-based system that gives different people access to different parts of the inventory system based on what they do.

## The Four User Types

### Admin - The Boss
Has access to everything. Can manage users, view all reports, and control the entire system.

**What they can do:**
- Dashboard, inventory, orders, sales, charts
- Create and manage user accounts
- Access all system features
- Full control over everything

### Supplier - The Stock Manager
Focuses on keeping inventory levels up and managing what comes in.

**What they can do:**
- Dashboard and inventory management
- View charts and reports
- Add and update stock levels
- **Can't access:** Orders, sales, or user management

### Store Clerk - The Organizer
Manages what's in stock and processes incoming orders.

**What they can do:**
- Dashboard and full inventory access
- Order management (view and process)
- Charts and reports
- **Can't access:** Sales or user management

### Cashier - The Sales Person
Handles customer transactions and sales.

**What they can do:**
- Dashboard access
- Create and process orders
- Record and view sales
- Charts and reports
- **Can't access:** Inventory management or user accounts

## Database Setup

When you first install the system, we automatically create an admin account:
- **Username:** admin
- **Password:** admin123
- **Role:** admin

For existing systems, run the migration script to add roles to your current users.

## How It Works

### Security Features
- Every page checks if you're logged in and have the right permissions
- Session-based authentication
- Automatic redirects if you don't have access
- Role validation on every protected page

### The Hierarchy
```
Admin (Everything)
├── Supplier (Inventory + Dashboard)
├── Store Clerk (Inventory + Orders + Dashboard)
└── Cashier (Orders + Sales + Dashboard)
```

---

# Sales Tracking Integration

We've connected the sales system to inventory tracking so every sale gets recorded properly.

## What Changed

When someone makes a sale (either through the website or API), the system now:
1. Creates the sale record
2. Updates the product quantity
3. **Creates an inventory transaction record** (this is new!)

This means you get a complete picture of where your products go.

## What Gets Tracked

Every sale creates a transaction record with:
- Which product was sold
- How many units
- Price per unit
- Who made the sale
- When it happened
- Whether it was through the web or API

## Why This Matters

### Complete Audit Trail
- See every product movement (both deliveries and sales)
- Track down discrepancies easily
- Have historical data for analysis

### Better Accountability
- Know who made which sales
- Track user performance
- Compliance with business requirements

### Data Integrity
- No missed transactions
- Consistent recording across all sales channels
- Proper database relationships

## How to Use It

### Making Sales Through the Website
1. Go to the sales page
2. Pick your product and quantity
3. Submit the sale
4. System handles everything automatically

### Making Sales Through the API
```bash
POST /api/sales.php?action=add
Authorization: Bearer {your_token}

{
    "product_id": 1,
    "quantity": 5,
    "sales_channel": "pos",
    "notes": "Point of sale transaction"
}
```

### Viewing Transaction History
Check the inventory transactions page to see all movements - both deliveries from suppliers and sales to customers.

---

# Order System Updates

We've made some changes to streamline the ordering process.

## What's New (May 27, 2025)

### Simplified Status Options
Instead of five different statuses, we now have three that matter:
- **Ordered** - The order has been placed
- **Delivered** - We received the products
- **Cancelled** - Order was cancelled

### Better Button Labels
Changed "New Supplier Order" to "Resupply Products" - makes more sense.

### Quick Add Features
You can now add suppliers and products on the fly:
- "New Supplier" button next to the supplier dropdown
- "New Product" button next to the product dropdown
- Both open quick forms to add what you need
- Page refreshes automatically so you can use the new items

### Smart Database Setup
The system now creates the suppliers table automatically if it doesn't exist, and includes some sample data to get you started.

## Files That Changed
- `order.php` - Main order management with all the new features
- Enhanced modal system for better user experience
- Better JavaScript for smooth interactions

## Ready to Use
✅ Streamlined order workflow
✅ Quick supplier/product creation
✅ Better user experience
✅ Auto-database setup

---

# Activity Logging System

We've built a comprehensive system that tracks when users log in and out, how long they spend in the system, and what they're doing.

## What It Does

### Tracks Everything
- Every login and logout
- How long each session lasts
- Which users are currently active
- Browser and device information

### Provides Tools for Admins
- Real-time activity dashboard
- Export data to CSV or JSON
- Filter by date, user, or activity type
- Clean up old logs

### Security Benefits
- See unusual login patterns
- Track session durations
- Monitor user behavior
- Compliance reporting

## How to Use It

### For Admins
1. Go to **Admin > Activity Logs** in the sidebar
2. View current statistics and active users
3. Filter logs to find what you need
4. Export data for analysis

### What You'll See
- Total logins over time
- Number of unique users
- Today's login activity
- Who's currently logged in
- Average session length

## Database Details

The system creates a `user_activity_logs` table that stores:
- User information
- Login/logout times
- Session duration
- IP addresses
- Browser information

## Files Involved

### New Files
- Activity logger class
- Admin dashboard
- Export functionality
- Testing scripts

### Modified Files
- Login/logout pages now log activity
- Sidebar has new activity logs link

Everything's working and ready for production use!

---

# Auto-Logout System

Nobody wants to leave their session open accidentally, so we built an automatic logout system.

## How It Works

The system automatically logs you out when:
- You close your browser tab or window
- You're inactive for 30 minutes
- Your connection is lost

## User Experience

### For Regular Use
- System runs quietly in the background
- You get a 5-minute warning before timeout
- Click "Stay Logged In" to extend your session
- Or click "Logout Now" to leave immediately

### For Admins
- View logout reasons in activity logs
- Monitor session patterns
- Track security events

## Security Benefits

1. **No Unattended Sessions**: Automatic cleanup prevents unauthorized access
2. **Browser Close Detection**: Logs you out immediately when you close tabs
3. **Activity Monitoring**: Complete logging for security audits
4. **Session Management**: Proper lifecycle management
5. **User-Friendly**: Warnings before automatic logout

## Configuration

- **Session Timeout**: 30 minutes of inactivity
- **Heartbeat Check**: Every 30 seconds
- **Warning Time**: 5 minutes before timeout
- **Activity Detection**: Mouse, keyboard, scroll, touch, click events

## What Gets Logged

The system tracks:
- Manual logouts
- Automatic timeouts
- Browser close events
- Session durations
- IP addresses and browser info

All pages in the system have this enabled except for data export scripts.

---

# Approval Workflow

We've added an approval step for supplier orders to make sure the right people sign off on purchases.

## How It Works

### The Process
1. Someone creates a supplier order
2. Order gets marked as "Pending Approval"
3. **Admin reviews and approves/rejects** (Store clerks can no longer approve)
4. Only approved orders can be shipped or delivered
5. Rejected orders get cancelled automatically

### Visual Cues
- Yellow badge for pending approval
- Green badge for approved orders
- Red badge for rejected orders
- Shows who approved and when

## Order Lifecycle

1. **Created** → Pending approval, status: pending
2. **Approved** → Approved, status: ordered (Admin only)
3. **Shipped** → Can only happen if approved
4. **Delivered** → Can only happen if approved
5. **Rejected** → Automatically cancelled (Admin only)

## Business Rules

- New orders always need approval first
- **Only administrators can approve or reject orders**
- Store clerks can view orders but cannot approve them
- Only approved orders can progress to shipping/delivery
- Rejected orders become cancelled
- Approval history is saved permanently
- Search and filter by approval status

## Using the System

### Creating Orders
1. Go to supplier orders
2. Click "Resupply Products"
3. Fill out the form
4. Order is created as "Pending Approval"

### Approving Orders (Admin Only)
1. **Only administrators can approve orders**
2. Look for yellow "Pending Approval" badges
3. Click the green checkmark to approve
4. Click the red X to reject
5. Confirm your choice
6. Status updates automatically

### For Store Clerks
- Can view all orders and their details
- Can create new orders (which will need admin approval)
- Can update status of already approved orders
- Cannot approve or reject orders - will see "Awaiting Admin Approval" badge
- Can perform other order management tasks (delete, update status)

### Processing Orders
- Only approved orders can be marked as shipped or delivered
- Use the edit button to update order status
- Enter received quantity when marking as delivered

## What Changed

### Database
- Added approval status column
- Added approved_by and approved_at columns
- Added database indexes for performance

### Interface
- New approval status column
- **Role-based approve/reject buttons (Admin only)**
- Color-coded badges
- Shows approver information
- **"Awaiting Admin Approval" badge for store clerks**

### Security & Permissions
- **Added server-side role validation for approval actions**
- Admin-only approval enforcement
- Graceful UI degradation for non-admin users
- Proper error messages for unauthorized access attempts

### Search & Filters
- Filter by approval status
- Search includes approval status
- Both filters work together

Everything's tested and ready to use!

---

## Security Update: Admin-Only Order Approvals

**Important Change:** Order approval permissions have been restricted to administrators only.

### What Changed
- **Previously:** Both admins and store clerks could approve supplier orders
- **Now:** Only administrators can approve or reject supplier orders
- **Store clerks** can still view, create, and manage orders but cannot approve them

### Why This Change
This update improves security and ensures proper approval hierarchy in the business workflow, where only administrators have the authority to approve significant purchases and supplier orders.

### User Experience
- **Admins:** No change - continue to see approve/reject buttons as before
- **Store Clerks:** See "Awaiting Admin Approval" badge instead of approval buttons
- **All Users:** Clear visual feedback about approval permissions

# Auto-Logout System Changes - Browser Close Only

## Summary of Changes Made

This document outlines the changes made to remove time-based auto-logout functionality and only keep browser close detection.

## Problem Solved

**Issue**: The auto-logout system was logging users out after clicking through approximately 5 pages due to aggressive session timeout checks (30-minute inactivity timeout with heartbeat monitoring every 2 minutes).

**Solution**: Completely removed all time-based session management and kept only browser close detection.

## Files Modified

### 1. `css/auto-logout.js` - Major Overhaul
**Before**: Complex system with heartbeat timers, session checks, warning modals, and 30-minute timeouts
**After**: Simple browser close detection only

**Key Changes**:
- Removed all timer-based functionality (heartbeat, session checks, warning timers)
- Removed session warning modal creation and display
- Removed activity detection that triggered heartbeats
- Removed session timeout logic
- Kept only `browserCloseDetection()` and `logBrowserClose()` methods
- Simplified `logout()` method for manual logout buttons

### 2. `api/auto_logout.php` - Simplified API
**Before**: Handled heartbeat, check_session, extend_session, and logout actions with complex timeout logic
**After**: Only handles logout action

**Key Changes**:
- Removed `heartbeat` action and all timeout checking
- Removed `check_session` action and session validation
- Removed `extend_session` action (no longer needed)
- Kept only `logout` action for browser close and manual logout
- Removed all session timeout calculations and grace periods

### 3. `config/auth.php` - Removed Session Activity Tracking
**Before**: Tracked `$_SESSION['last_activity']` and updated it periodically
**After**: No time-based session tracking

**Key Changes**:
- Removed `$_SESSION['last_activity']` initialization and updates
- Added comment explaining that time-based session management is disabled
- Kept all other authentication functions unchanged

### 4. `login.php` - Cleaned Up Session Variables
**Before**: Set `$_SESSION['last_activity']` on successful login
**After**: No activity tracking

**Key Changes**:
- Removed `$_SESSION['last_activity'] = time();` line
- Kept all other login functionality unchanged

## New Files Created

### 5. `test_no_timeout.php` - Testing Page
- Created comprehensive test page to verify the changes
- Shows session information and confirms no `last_activity` tracking
- Provides navigation links to test browsing between pages
- Includes browser close detection test button
- Real-time activity logging to monitor system behavior

## System Behavior Now

### ✅ What Works:
1. **No Time-Based Logout**: Users can navigate between pages indefinitely without being logged out
2. **Browser Close Detection**: Closing browser tab/window properly logs the user out
3. **Manual Logout**: Logout buttons still work correctly
4. **Session Persistence**: Sessions persist until browser is closed or manual logout
5. **Activity Logging**: All logout events are still properly logged with reasons

### ✅ What's Removed:
1. **30-Minute Timeout**: No more automatic logout after inactivity
2. **Heartbeat Checks**: No more periodic server requests to check session status
3. **Session Warnings**: No more "session expiring soon" popup modals
4. **Activity Detection**: No more mouse/keyboard activity monitoring for session extension

## Testing Instructions

1. **Login** to the system normally
2. **Navigate** between multiple pages (dashboard, inventory, sales, etc.)
3. **Verify** you are NOT logged out after any amount of time or page navigation
4. **Test Browser Close**: Close the browser tab/window and check activity logs
5. **Test Manual Logout**: Use logout button to ensure it still works
6. **Use Test Page**: Visit `test_no_timeout.php` for comprehensive testing

## Benefits

1. **No More Premature Logouts**: Users won't be logged out while actively using the system
2. **Better User Experience**: No interruptions or timeout warnings
3. **Reduced Server Load**: No more periodic heartbeat requests
4. **Simplified Code**: Much simpler and more maintainable code
5. **Faster Performance**: No background timers or activity monitoring

## Security Considerations

- **Browser Close Detection**: Still logs users out when browser is closed for security
- **Manual Logout**: Users can still manually log out at any time
- **Session Security**: Standard PHP session security measures remain in place
- **Activity Logging**: All logout events are still tracked for audit purposes

## Rollback Information

If you need to restore the original time-based system, the key changes to revert are:
1. Restore the original `css/auto-logout.js` with timer functionality
2. Restore the original `api/auto_logout.php` with heartbeat/check_session actions
3. Restore `$_SESSION['last_activity']` tracking in `config/auth.php` and `login.php`

---

**Date Modified**: <?php echo date('Y-m-d H:i:s'); ?>  
**Modified By**: System Administrator  
**Reason**: Remove premature auto-logout behavior, keep only browser close detection
