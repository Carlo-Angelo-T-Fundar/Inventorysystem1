-- MySQL Database Dump
-- Generated on: 2025-06-01 01:47:31
-- Database: inventory_db
-- Host: localhost

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- Database: `inventory_db`
CREATE DATABASE IF NOT EXISTS `inventory_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `inventory_db`;

-- --------------------------------------------------------
-- Table structure for table `api_tokens`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `api_tokens`;
CREATE TABLE `api_tokens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `token` varchar(255) NOT NULL,
  `expiry` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `api_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table `api_tokens`
-- --------------------------------------------------------

INSERT INTO `api_tokens` VALUES
('2', '1', 'caa7182c0cf7afff01383ee4951b31497b8a17f7e21e479e46d8cfe2f488ca95', '2025-05-27 00:43:48', '2025-05-25 16:43:48');

-- --------------------------------------------------------
-- Table structure for table `inventory_transactions`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `inventory_transactions`;
CREATE TABLE `inventory_transactions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `transaction_type` enum('delivery','sale','adjustment','return') NOT NULL DEFAULT 'delivery',
  `quantity` int NOT NULL,
  `unit_price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_value` decimal(10,2) NOT NULL DEFAULT '0.00',
  `supplier_order_id` int DEFAULT NULL,
  `supplier_name` varchar(255) DEFAULT NULL,
  `batch_number` varchar(100) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `transaction_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `notes` text,
  `created_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_product_id` (`product_id`),
  KEY `idx_transaction_type` (`transaction_type`),
  KEY `idx_transaction_date` (`transaction_date`),
  KEY `idx_supplier_order_id` (`supplier_order_id`),
  CONSTRAINT `inventory_transactions_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table `inventory_transactions`
-- --------------------------------------------------------

INSERT INTO `inventory_transactions` VALUES
('1', '4', 'Bluetooth Headphones', 'delivery', '25', '120.00', '3000.00', '5', 'Audio Tech Ltd', NULL, NULL, '2025-05-26 12:28:47', 'Delivered from supplier order', 'admin', '2025-05-26 12:28:47'),
('2', '4', 'Bluetooth Headphones', 'sale', '1', '149.99', '149.99', NULL, NULL, NULL, NULL, '2025-05-27 12:29:29', 'Sale transaction', 'admin', '2025-05-27 12:29:29'),
('3', '4', 'Bluetooth Headphones', 'delivery', '12', '12.00', '144.00', '6', 'Accessory World', NULL, NULL, '2025-05-31 13:21:03', 'Delivered from supplier order', 'admin', '2025-05-31 13:21:03'),
('4', '4', 'Bluetooth Headphones', 'sale', '50', '149.99', '7499.50', NULL, NULL, NULL, NULL, '2025-05-31 13:41:57', 'Sale transaction', 'admin', '2025-05-31 13:41:57'),
('6', '4', 'Bluetooth Headphones', 'sale', '46', '149.99', '6899.54', NULL, NULL, NULL, NULL, '2025-05-31 14:21:40', 'Sale transaction', 'admin', '2025-05-31 14:21:40'),
('7', '3', 'USB-C Cable', 'delivery', '100', '15.00', '1500.00', '4', 'Cable Solutions', NULL, NULL, '2025-05-31 15:22:56', 'Delivered from supplier order', 'admin', '2025-05-31 15:22:56');

-- --------------------------------------------------------
-- Table structure for table `items`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `items`;
CREATE TABLE `items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text,
  `quantity` int NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_item_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table `items`
-- --------------------------------------------------------

INSERT INTO `items` VALUES
('1', 'Office Chair', 'Ergonomic office chair with lumbar support', '25', '199.99', '2025-05-25 14:27:47'),
('2', 'Desk Lamp', 'LED desk lamp with adjustable brightness', '40', '49.99', '2025-05-25 14:27:47'),
('3', 'Filing Cabinet', '4-drawer metal filing cabinet', '15', '149.99', '2025-05-25 14:27:47'),
('4', 'Whiteboard', 'Magnetic dry erase whiteboard 48x36', '8', '89.99', '2025-05-25 14:27:47'),
('5', 'Paper Shredder', 'Cross-cut paper shredder for office use', '12', '129.99', '2025-05-25 14:27:47');

-- --------------------------------------------------------
-- Table structure for table `low_stock_products`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `low_stock_products`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `low_stock_products` AS select `products`.`id` AS `id`,`products`.`name` AS `name`,`products`.`quantity` AS `quantity`,`products`.`alert_quantity` AS `alert_quantity`,`products`.`price` AS `price`,(case when (`products`.`quantity` = 0) then 'Out of Stock' when (`products`.`quantity` <= (`products`.`alert_quantity` / 2)) then 'Critical' when (`products`.`quantity` <= `products`.`alert_quantity`) then 'Low' else 'Normal' end) AS `stock_status` from `products` where (`products`.`quantity` <= `products`.`alert_quantity`) order by `products`.`quantity`;

-- Dumping data for table `low_stock_products`
-- --------------------------------------------------------

INSERT INTO `low_stock_products` VALUES
('4', 'Bluetooth Headphones', '0', '8', '149.99', 'Out of Stock'),
('1', 'Laptop - Dell XPS 13', '5', '5', '899.99', 'Low');

-- --------------------------------------------------------
-- Table structure for table `order_items`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `order_items`;
CREATE TABLE `order_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `order_id` int NOT NULL,
  `product_id` int NOT NULL,
  `quantity` int NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_order_product` (`order_id`,`product_id`),
  KEY `idx_order_id` (`order_id`),
  KEY `idx_product_id` (`product_id`),
  KEY `idx_order_items_composite` (`order_id`,`product_id`,`quantity`),
  CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table `order_items`
-- --------------------------------------------------------

INSERT INTO `order_items` VALUES
('1', '1', '1', '1', '899.99', '2025-05-25 14:27:47'),
('2', '1', '4', '1', '149.99', '2025-05-25 14:27:47'),
('3', '2', '2', '2', '29.99', '2025-05-25 14:27:47'),
('4', '2', '3', '3', '19.99', '2025-05-25 14:27:47'),
('5', '2', '9', '2', '39.99', '2025-05-25 14:27:47'),
('6', '3', '6', '1', '599.99', '2025-05-25 14:27:47'),
('8', '5', '5', '1', '999.99', '2025-05-25 14:27:47'),
('9', '5', '10', '1', '79.99', '2025-05-25 14:27:47'),
('19', '6', '3', '20', '19.99', '2025-05-25 14:49:01'),
('20', '8', '1', '2', '29.99', '2025-05-25 18:08:39'),
('21', '8', '2', '1', '19.99', '2025-05-25 18:08:39'),
('22', '9', '1', '2', '29.99', '2025-05-25 18:23:41'),
('23', '9', '2', '1', '19.99', '2025-05-25 18:23:41'),
('24', '10', '1', '2', '29.99', '2025-05-25 18:36:03'),
('25', '10', '2', '1', '19.99', '2025-05-25 18:36:03'),
('26', '11', '1', '2', '29.99', '2025-05-25 18:36:31'),
('27', '11', '2', '1', '19.99', '2025-05-25 18:36:31'),
('28', '12', '1', '2', '29.99', '2025-05-25 18:44:16'),
('29', '12', '2', '1', '19.99', '2025-05-25 18:44:16'),
('30', '13', '11', '2', '55.00', '2025-05-26 11:12:41'),
('31', '14', '4', '1', '149.99', '2025-05-27 12:29:29'),
('32', '15', '4', '50', '149.99', '2025-05-31 13:41:57'),
('33', '16', '4', '46', '149.99', '2025-05-31 14:21:40');

-- --------------------------------------------------------
-- Table structure for table `orders`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT '1',
  `total_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `status` enum('pending','processing','completed','cancelled') NOT NULL DEFAULT 'pending',
  `sales_channel` varchar(50) DEFAULT 'Store',
  `destination` varchar(255) DEFAULT 'Lagao',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_sales_channel` (`sales_channel`),
  KEY `idx_order_status_date` (`status`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table `orders`
-- --------------------------------------------------------

INSERT INTO `orders` VALUES
('1', '1', '1049.98', 'completed', 'online', 'Kathmandu', '2024-01-15 10:30:00', '2025-05-25 14:27:47'),
('2', '1', '179.98', 'completed', 'store', 'Lalitpur', '2024-01-15 14:20:00', '2025-05-25 14:27:47'),
('3', '1', '599.99', 'completed', 'online', 'Pokhara', '2024-01-16 09:15:00', '2025-05-25 14:42:45'),
('4', '1', '89.99', 'completed', 'store', 'Lalitpur', '2024-01-16 16:45:00', '2025-05-25 14:42:40'),
('5', '1', '1299.98', 'completed', 'online', 'Biratnagar', '2024-01-17 11:20:00', '2025-05-25 14:27:47'),
('6', '1', '399.80', 'processing', 'store', 'Lalitpur', '2025-05-25 14:49:01', '2025-05-25 17:20:22'),
('7', '1', '0.00', 'pending', 'Sari-Sari Store', 'Lagao', '2025-05-25 17:44:18', '2025-05-25 17:44:18'),
('8', '1', '79.97', 'completed', 'Sari-Sari Store', 'Lagao', '2025-05-25 18:08:39', '2025-05-25 18:22:27'),
('9', '1', '79.97', 'pending', 'Sari-Sari Store', 'Lagao', '2025-05-25 18:23:41', '2025-05-25 18:23:41'),
('10', '1', '79.97', 'pending', 'Sari-Sari Store', 'Lagao', '2025-05-25 18:36:03', '2025-05-25 18:36:03'),
('11', '1', '79.97', 'pending', 'Sari-Sari Store', 'Lagao', '2025-05-25 18:36:31', '2025-05-25 18:36:31'),
('12', '1', '79.97', 'pending', 'Store', 'Lagao Village', '2025-05-25 18:44:16', '2025-05-25 18:44:16'),
('13', '1', '110.00', 'completed', 'Store', 'Lagao', '2025-05-26 11:12:41', '2025-05-26 11:12:41'),
('14', '1', '149.99', 'completed', 'Store', 'Lagao', '2025-05-27 12:29:29', '2025-05-27 12:29:29'),
('15', '1', '7499.50', 'completed', 'Store', 'Lagao', '2025-05-31 13:41:57', '2025-05-31 13:41:57'),
('16', '1', '6899.54', 'completed', 'Store', 'Lagao', '2025-05-31 14:21:40', '2025-05-31 14:21:40');

-- --------------------------------------------------------
-- Table structure for table `product_audit_log`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `product_audit_log`;
CREATE TABLE `product_audit_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int DEFAULT NULL,
  `old_quantity` int DEFAULT NULL,
  `new_quantity` int DEFAULT NULL,
  `change_type` varchar(50) DEFAULT NULL,
  `changed_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `product_audit_log_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table `product_audit_log`
-- --------------------------------------------------------

INSERT INTO `product_audit_log` VALUES
('1', '3', '50', '30', 'QUANTITY_UPDATE', '2025-05-25 14:49:01'),
('3', '4', '20', '7', 'QUANTITY_UPDATE', '2025-05-25 17:17:16'),
('4', '4', '7', '10', 'QUANTITY_UPDATE', '2025-05-25 17:17:26'),
('6', '1', '15', '13', 'QUANTITY_UPDATE', '2025-05-25 18:08:39'),
('7', '2', '25', '24', 'QUANTITY_UPDATE', '2025-05-25 18:08:39'),
('8', '1', '13', '11', 'QUANTITY_UPDATE', '2025-05-25 18:23:41'),
('9', '2', '24', '23', 'QUANTITY_UPDATE', '2025-05-25 18:23:41'),
('10', '1', '11', '9', 'QUANTITY_UPDATE', '2025-05-25 18:36:03'),
('11', '2', '23', '22', 'QUANTITY_UPDATE', '2025-05-25 18:36:03'),
('12', '1', '9', '7', 'QUANTITY_UPDATE', '2025-05-25 18:36:31'),
('13', '2', '22', '21', 'QUANTITY_UPDATE', '2025-05-25 18:36:31'),
('14', '1', '7', '5', 'QUANTITY_UPDATE', '2025-05-25 18:44:16'),
('15', '2', '21', '20', 'QUANTITY_UPDATE', '2025-05-25 18:44:16'),
('16', '11', '10', '5', 'QUANTITY_UPDATE', '2025-05-26 11:02:43'),
('18', '11', '5', '3', 'QUANTITY_UPDATE', '2025-05-26 11:12:41'),
('19', '4', '10', '60', 'QUANTITY_UPDATE', '2025-05-26 12:12:55'),
('20', '4', '60', '85', 'QUANTITY_UPDATE', '2025-05-26 12:28:47'),
('21', '4', '85', '84', 'QUANTITY_UPDATE', '2025-05-27 12:29:29'),
('22', '4', '84', '96', 'QUANTITY_UPDATE', '2025-05-31 13:21:03'),
('23', '4', '96', '46', 'QUANTITY_UPDATE', '2025-05-31 13:41:57'),
('24', '4', '46', '0', 'QUANTITY_UPDATE', '2025-05-31 14:21:40'),
('25', '3', '30', '130', 'QUANTITY_UPDATE', '2025-05-31 15:22:56');

-- --------------------------------------------------------
-- Table structure for table `product_sales_performance`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `product_sales_performance`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `product_sales_performance` AS select `p`.`id` AS `id`,`p`.`name` AS `name`,`p`.`price` AS `price`,`p`.`quantity` AS `current_stock`,coalesce(sum(`oi`.`quantity`),0) AS `total_sold`,coalesce(sum((`oi`.`quantity` * `oi`.`price`)),0) AS `total_revenue`,count(distinct `oi`.`order_id`) AS `orders_count` from ((`products` `p` left join `order_items` `oi` on((`p`.`id` = `oi`.`product_id`))) left join `orders` `o` on(((`oi`.`order_id` = `o`.`id`) and (`o`.`status` = 'completed')))) group by `p`.`id`,`p`.`name`,`p`.`price`,`p`.`quantity` order by `total_sold` desc;

-- Dumping data for table `product_sales_performance`
-- --------------------------------------------------------

INSERT INTO `product_sales_performance` VALUES
('4', 'Bluetooth Headphones', '149.99', '0', '98', '14699.02', '4'),
('3', 'USB-C Cable', '19.99', '130', '23', '459.77', '2'),
('1', 'Laptop - Dell XPS 13', '899.99', '5', '11', '1199.89', '6'),
('2', 'Wireless Mouse', '29.99', '20', '7', '159.93', '6'),
('9', 'Power Bank 10000mAh', '39.99', '30', '2', '79.98', '1'),
('11', 'Piattos', '55.00', '3', '2', '110.00', '1'),
('5', 'Smartphone - iPhone 14', '999.99', '8', '1', '999.99', '1'),
('6', 'Tablet - iPad Air', '599.99', '12', '1', '599.99', '1'),
('10', 'Webcam HD 1080p', '79.99', '15', '1', '79.99', '1');

-- --------------------------------------------------------
-- Table structure for table `products`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `quantity` int NOT NULL DEFAULT '0',
  `quantity_arrived` int DEFAULT '0',
  `alert_quantity` int NOT NULL DEFAULT '10',
  `price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `arrival_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_name` (`name`),
  KEY `idx_quantity` (`quantity`),
  KEY `idx_alert_quantity` (`alert_quantity`),
  KEY `idx_product_name_quantity` (`name`,`quantity`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table `products`
-- --------------------------------------------------------

INSERT INTO `products` VALUES
('1', 'Laptop - Dell XPS 13', '5', '5', '5', '899.99', '2025-05-26 11:01:50', '2025-05-25 14:27:47', '2025-05-26 11:01:50'),
('2', 'Wireless Mouse', '20', '20', '10', '29.99', '2025-05-26 11:01:50', '2025-05-25 14:27:47', '2025-05-26 11:01:50'),
('3', 'USB-C Cable', '130', '30', '15', '19.99', '2025-05-26 11:01:50', '2025-05-25 14:27:47', '2025-05-31 15:22:56'),
('4', 'Bluetooth Headphones', '0', '10', '8', '149.99', '2025-05-26 11:01:50', '2025-05-25 14:27:47', '2025-05-31 14:21:40'),
('5', 'Smartphone - iPhone 14', '8', '8', '3', '999.99', '2025-05-26 11:01:50', '2025-05-25 14:27:47', '2025-05-26 11:01:50'),
('6', 'Tablet - iPad Air', '12', '12', '5', '599.99', '2025-05-26 11:01:50', '2025-05-25 14:27:47', '2025-05-26 11:01:50'),
('9', 'Power Bank 10000mAh', '30', '30', '12', '39.99', '2025-05-26 11:01:50', '2025-05-25 14:27:47', '2025-05-26 11:01:50'),
('10', 'Webcam HD 1080p', '15', '15', '6', '79.99', '2025-05-26 11:01:50', '2025-05-25 14:27:47', '2025-05-26 11:01:50'),
('11', 'Piattos', '3', '10', '2', '55.00', '2025-05-26 11:02:26', '2025-05-26 11:02:26', '2025-05-26 11:12:41');

-- --------------------------------------------------------
-- Table structure for table `sales_summary`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `sales_summary`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `sales_summary` AS select cast(`o`.`created_at` as date) AS `sale_date`,count(`o`.`id`) AS `total_orders`,sum(`o`.`total_amount`) AS `total_revenue`,avg(`o`.`total_amount`) AS `average_order_value`,sum(`oi`.`quantity`) AS `total_items_sold` from (`orders` `o` left join `order_items` `oi` on((`o`.`id` = `oi`.`order_id`))) where (`o`.`status` = 'completed') group by cast(`o`.`created_at` as date) order by `sale_date` desc;

-- Dumping data for table `sales_summary`
-- --------------------------------------------------------

INSERT INTO `sales_summary` VALUES
('2025-05-31', '2', '14399.04', '7199.520000', '96'),
('2025-05-27', '1', '149.99', '149.990000', '1'),
('2025-05-26', '1', '110.00', '110.000000', '2'),
('2025-05-25', '2', '159.94', '79.970000', '3'),
('2024-01-17', '2', '2599.96', '1299.980000', '2'),
('2024-01-16', '2', '689.98', '344.990000', '1'),
('2024-01-15', '5', '2639.90', '527.980000', '9');

-- --------------------------------------------------------
-- Table structure for table `supplier_order_summary`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `supplier_order_summary`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `supplier_order_summary` AS select `so`.`id` AS `id`,`so`.`supplier_name` AS `supplier_name`,`so`.`supplier_email` AS `supplier_email`,`so`.`supplier_phone` AS `supplier_phone`,`so`.`product_name` AS `product_name`,`so`.`quantity_ordered` AS `quantity_ordered`,`so`.`quantity_received` AS `quantity_received`,`so`.`unit_price` AS `unit_price`,`so`.`total_amount` AS `total_amount`,`so`.`order_date` AS `order_date`,`so`.`expected_delivery_date` AS `expected_delivery_date`,`so`.`actual_delivery_date` AS `actual_delivery_date`,`so`.`status` AS `status`,`so`.`notes` AS `notes`,(case when ((`so`.`status` = 'delivered') and (`so`.`quantity_received` = `so`.`quantity_ordered`)) then 'Complete' when ((`so`.`status` = 'delivered') and (`so`.`quantity_received` < `so`.`quantity_ordered`)) then 'Partial' when (`so`.`status` = 'cancelled') then 'Cancelled' when ((`so`.`expected_delivery_date` < curdate()) and (`so`.`status` <> 'delivered')) then 'Overdue' else 'On Track' end) AS `delivery_status` from `supplier_orders` `so` order by `so`.`order_date` desc;

-- Dumping data for table `supplier_order_summary`
-- --------------------------------------------------------

INSERT INTO `supplier_order_summary` VALUES
('7', 'Accessory World', 'sales@accessoryworld.com', '+1-555-0102', 'Bluetooth Headphones', '25', '12', '12.00', '300.00', '2025-05-31 14:19:01', '2025-05-31', '2025-05-31', 'delivered', '', 'Partial'),
('6', 'Accessory World', 'sales@accessoryworld.com', '+1-555-0102', 'Bluetooth Headphones', '50', '12', '12.00', '600.00', '2025-05-26 12:01:16', '2025-06-21', '2025-05-31', 'delivered', '', 'Partial'),
('4', 'Cable Solutions', 'info@cablesolutions.com', '+1-555-0104', 'USB-C Cable', '100', '100', '15.00', '1500.00', '2024-01-16 11:45:00', '2024-01-22', '2025-05-31', 'delivered', 'Awaiting stock confirmation', 'Complete'),
('3', 'Mobile Plus Inc', 'support@mobileplus.com', '+1-555-0103', 'Smartphone - iPhone 14', '5', '0', '900.00', '4500.00', '2024-01-15 14:20:00', '2024-01-25', NULL, 'shipped', 'In transit via FedEx', 'Overdue'),
('2', 'Accessory World', 'sales@accessoryworld.com', '+1-555-0102', 'Wireless Mouse', '50', '50', '25.00', '1250.00', '2024-01-12 10:30:00', '2024-01-18', NULL, 'delivered', 'Express delivery completed', 'Complete'),
('1', 'TechSupply Corp', 'orders@techsupply.com', '+1-555-0101', 'Laptop - Dell XPS 13', '10', '0', '850.00', '8500.00', '2024-01-10 09:00:00', '2024-01-20', NULL, 'ordered', 'Bulk order for Q1 stock', 'Overdue');

-- --------------------------------------------------------
-- Table structure for table `supplier_orders`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `supplier_orders`;
CREATE TABLE `supplier_orders` (
  `id` int NOT NULL AUTO_INCREMENT,
  `supplier_name` varchar(255) NOT NULL,
  `supplier_email` varchar(255) NOT NULL,
  `supplier_phone` varchar(50) NOT NULL,
  `product_id` int NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `quantity_ordered` int NOT NULL DEFAULT '0',
  `quantity_received` int NOT NULL DEFAULT '0',
  `unit_price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `order_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `expected_delivery_date` date DEFAULT NULL,
  `actual_delivery_date` date DEFAULT NULL,
  `status` enum('pending','ordered','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending',
  `approval_status` enum('pending_approval','approved','rejected') NOT NULL DEFAULT 'pending_approval',
  `approved_by` int DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_supplier_name` (`supplier_name`),
  KEY `idx_product_id` (`product_id`),
  KEY `idx_status` (`status`),
  KEY `idx_order_date` (`order_date`),
  KEY `idx_approval_status` (`approval_status`),
  KEY `approved_by` (`approved_by`),
  CONSTRAINT `supplier_orders_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `supplier_orders_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table `supplier_orders`
-- --------------------------------------------------------

INSERT INTO `supplier_orders` VALUES
('1', 'TechSupply Corp', 'orders@techsupply.com', '+1-555-0101', '1', 'Laptop - Dell XPS 13', '10', '0', '850.00', '8500.00', '2024-01-10 09:00:00', '2024-01-20', NULL, 'ordered', 'pending_approval', NULL, NULL, 'Bulk order for Q1 stock', '2025-05-26 11:44:58', '2025-05-26 11:44:58'),
('2', 'Accessory World', 'sales@accessoryworld.com', '+1-555-0102', '2', 'Wireless Mouse', '50', '50', '25.00', '1250.00', '2024-01-12 10:30:00', '2024-01-18', NULL, 'delivered', 'pending_approval', NULL, NULL, 'Express delivery completed', '2025-05-26 11:44:58', '2025-05-26 11:44:58'),
('3', 'Mobile Plus Inc', 'support@mobileplus.com', '+1-555-0103', '5', 'Smartphone - iPhone 14', '5', '0', '900.00', '4500.00', '2024-01-15 14:20:00', '2024-01-25', NULL, 'shipped', 'pending_approval', NULL, NULL, 'In transit via FedEx', '2025-05-26 11:44:58', '2025-05-26 11:44:58'),
('4', 'Cable Solutions', 'info@cablesolutions.com', '+1-555-0104', '3', 'USB-C Cable', '100', '100', '15.00', '1500.00', '2024-01-16 11:45:00', '2024-01-22', '2025-05-31', 'delivered', 'approved', '1', '2025-05-31 14:27:15', 'Awaiting stock confirmation', '2025-05-26 11:44:58', '2025-05-31 15:22:56'),
('6', 'Accessory World', 'sales@accessoryworld.com', '+1-555-0102', '4', 'Bluetooth Headphones', '50', '12', '12.00', '600.00', '2025-05-26 12:01:16', '2025-06-21', '2025-05-31', 'delivered', 'approved', '1', '2025-05-27 22:53:15', '', '2025-05-26 12:01:16', '2025-05-31 13:21:03'),
('7', 'Accessory World', 'sales@accessoryworld.com', '+1-555-0102', '4', 'Bluetooth Headphones', '25', '12', '12.00', '300.00', '2025-05-31 14:19:01', '2025-05-31', '2025-05-31', 'delivered', 'approved', '1', '2025-05-31 14:19:28', '', '2025-05-31 14:19:01', '2025-05-31 14:19:44');

-- --------------------------------------------------------
-- Table structure for table `suppliers`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `suppliers`;
CREATE TABLE `suppliers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(50) NOT NULL,
  `address` text,
  `payment_terms` varchar(100) DEFAULT NULL,
  `notes` text,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `idx_name` (`name`),
  KEY `idx_email` (`email`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table `suppliers`
-- --------------------------------------------------------

INSERT INTO `suppliers` VALUES
('1', 'TechSupply Corp', 'John Anderson', 'orders@techsupply.com', '+1-555-0101', '123 Tech Street, Silicon Valley, CA', 'Net 30', NULL, '1', '2025-05-26 11:44:58', '2025-05-26 11:44:58'),
('2', 'Accessory World', 'Sarah Johnson', 'sales@accessoryworld.com', '+1-555-0102', '456 Accessory Ave, New York, NY', 'Net 15', NULL, '1', '2025-05-26 11:44:58', '2025-05-26 11:44:58'),
('3', 'Mobile Plus Inc', 'Mike Chen', 'support@mobileplus.com', '+1-555-0103', '789 Mobile Blvd, Austin, TX', 'Net 30', NULL, '1', '2025-05-26 11:44:58', '2025-05-26 11:44:58'),
('4', 'Cable Solutions', 'Lisa Rodriguez', 'info@cablesolutions.com', '+1-555-0104', '321 Cable Drive, Portland, OR', 'COD', NULL, '1', '2025-05-26 11:44:58', '2025-05-26 11:44:58'),
('5', 'Audio Tech Ltd', 'David Wilson', 'orders@audiotech.com', '+1-555-0105', '654 Audio Lane, Nashville, TN', 'Net 30', NULL, '1', '2025-05-26 11:44:58', '2025-05-26 11:44:58');

-- --------------------------------------------------------
-- Table structure for table `user_activity_logs`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `user_activity_logs`;
CREATE TABLE `user_activity_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `username` varchar(50) NOT NULL,
  `user_role` varchar(20) NOT NULL,
  `activity_type` enum('login','logout','session_timeout','browser_close') NOT NULL,
  `login_time` timestamp NULL DEFAULT NULL,
  `logout_time` timestamp NULL DEFAULT NULL,
  `session_duration` int DEFAULT NULL COMMENT 'Duration in seconds',
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_activity_type` (`activity_type`),
  KEY `idx_login_time` (`login_time`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `user_activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=49 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table `user_activity_logs`
-- --------------------------------------------------------

INSERT INTO `user_activity_logs` VALUES
('1', '1', 'admin', 'admin', 'logout', '2025-05-27 21:21:57', '2025-05-27 21:27:39', '342', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-27 13:21:57'),
('2', '1', 'admin', 'admin', 'login', '2025-05-27 21:27:44', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-27 13:27:44'),
('3', '1', 'admin', 'admin', 'logout', '2025-05-28 00:42:16', '2025-05-28 01:13:28', '1872', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-27 16:42:16'),
('4', '1', 'admin', 'admin', 'session_timeout', '2025-05-28 03:43:18', '2025-05-28 03:43:21', '3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-27 19:43:18'),
('5', '1', 'admin', 'admin', 'session_timeout', '2025-05-28 03:43:36', '2025-05-28 03:43:39', '3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-27 19:43:36'),
('6', '1', 'admin', 'admin', 'logout', '2025-05-28 03:56:52', '2025-05-28 03:58:48', '116', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-27 19:56:52'),
('7', '1', 'admin', 'admin', 'login', '2025-05-28 05:42:57', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-27 21:42:57'),
('8', '1', 'admin', 'admin', 'login', '2025-05-28 06:06:29', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-27 22:06:29'),
('9', '1', 'admin', 'admin', 'logout', '2025-05-28 06:09:31', '2025-05-28 06:19:47', '616', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-27 22:09:31'),
('10', '9', 'carlo3', 'store_clerk', 'logout', '2025-05-28 06:19:53', '2025-05-28 06:21:03', '70', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-27 22:19:53'),
('11', '1', 'admin', 'admin', 'logout', '2025-05-28 06:21:10', '2025-05-28 06:21:35', '25', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-27 22:21:10'),
('12', '4', 'cashier1', 'cashier', 'logout', '2025-05-28 06:21:41', '2025-05-28 06:24:42', '181', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-27 22:21:41'),
('13', '1', 'admin', 'admin', 'logout', '2025-05-28 06:24:48', '2025-05-28 06:53:26', '1718', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-27 22:24:48'),
('14', '9', 'carlo3', 'store_clerk', 'logout', '2025-05-28 06:53:41', '2025-05-28 06:54:00', '19', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-27 22:53:41'),
('15', '4', 'cashier1', 'cashier', 'logout', '2025-05-28 06:54:10', '2025-05-28 06:54:34', '24', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-27 22:54:10'),
('16', '1', 'admin', 'admin', 'logout', '2025-05-28 06:54:41', '2025-05-28 07:06:59', '738', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-27 22:54:41'),
('17', '4', 'cashier1', 'cashier', 'logout', '2025-05-28 07:07:07', '2025-05-28 07:09:28', '141', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-27 23:07:07'),
('18', '4', 'cashier1', 'cashier', 'logout', '2025-05-28 07:14:58', '2025-05-28 07:15:06', '8', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-27 23:14:58'),
('19', '1', 'admin', 'admin', 'logout', '2025-05-29 00:35:17', '2025-05-29 00:39:53', '276', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-28 16:35:17'),
('20', '1', 'admin', 'admin', 'login', '2025-05-29 00:40:33', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-28 16:40:33'),
('21', '1', 'admin', 'admin', 'logout', '2025-05-29 18:29:26', '2025-05-29 18:38:09', '523', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-29 10:29:26'),
('22', '9', 'carlo3', 'store_clerk', 'logout', '2025-05-29 18:38:14', '2025-05-29 18:40:08', '114', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-29 10:38:14'),
('23', '1', 'admin', 'admin', 'login', '2025-05-29 18:40:13', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-29 10:40:13'),
('24', '1', 'admin', 'admin', 'session_timeout', '2025-05-29 18:52:58', '2025-05-29 18:58:13', '315', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-29 10:52:58'),
('25', '1', 'admin', 'admin', 'session_timeout', '2025-05-29 19:07:41', '2025-05-29 19:07:42', '1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-29 11:07:41'),
('26', '1', 'admin', 'admin', 'session_timeout', '2025-05-29 19:07:47', '2025-05-29 19:07:49', '2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-29 11:07:47'),
('27', '1', 'admin', 'admin', 'session_timeout', '2025-05-29 19:08:19', '2025-05-29 19:08:20', '1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-29 11:08:19'),
('28', '1', 'admin', 'admin', 'session_timeout', '2025-05-29 19:08:27', '2025-05-29 19:08:28', '1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-29 11:08:27'),
('29', '1', 'admin', 'admin', 'login', '2025-05-29 19:16:02', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-29 11:16:02'),
('30', '1', 'admin', 'admin', 'browser_close', '2025-05-29 19:16:18', '2025-05-29 19:16:51', '33', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-29 11:16:18'),
('31', '1', 'admin', 'admin', 'login', '2025-05-29 19:44:23', '2025-05-29 19:44:34', '11', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-29 11:44:23'),
('32', '1', 'admin', 'admin', 'login', '2025-05-29 19:44:39', '2025-05-29 19:44:54', '15', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-29 11:44:39'),
('33', '1', 'admin', 'admin', 'login', '2025-05-29 19:45:01', '2025-05-29 19:45:32', '31', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-29 11:45:01'),
('34', '1', 'admin', 'admin', 'login', '2025-05-29 20:08:14', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-29 12:08:14'),
('35', '1', 'admin', 'admin', 'login', '2025-05-29 20:08:31', '2025-05-29 20:17:21', '530', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-29 12:08:31'),
('36', '1', 'admin', 'admin', 'login', '2025-05-29 20:28:17', '2025-05-29 20:28:20', '3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-29 12:28:17'),
('37', '1', 'admin', 'admin', 'login', '2025-05-29 20:28:26', '2025-05-29 20:28:29', '3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-29 12:28:26'),
('38', '1', 'admin', 'admin', 'login', '2025-05-29 20:29:48', '2025-05-29 20:29:51', '3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-29 12:29:48'),
('39', '1', 'admin', 'admin', 'logout', '2025-05-29 20:32:44', '2025-05-29 20:35:35', '171', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-29 12:32:44'),
('40', '4', 'cashier1', 'cashier', 'login', '2025-05-29 20:35:40', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-29 12:35:40'),
('41', '4', 'cashier1', 'cashier', 'logout', '2025-05-29 20:38:15', '2025-05-29 20:38:35', '20', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-29 12:38:15'),
('42', '9', 'carlo3', 'store_clerk', 'logout', '2025-05-29 20:38:41', '2025-05-29 20:38:55', '14', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-29 12:38:41'),
('43', '1', 'admin', 'admin', 'login', '2025-05-30 18:18:24', '2025-05-30 18:20:11', '107', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-30 10:18:24'),
('44', '9', 'carlo3', 'store_clerk', 'login', '2025-05-30 18:20:16', '2025-05-30 18:21:46', '90', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-30 10:20:16'),
('45', '1', 'admin', 'admin', 'login', '2025-05-30 18:21:50', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-30 10:21:50'),
('46', '1', 'admin', 'admin', 'login', '2025-05-31 03:42:52', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-30 19:42:52'),
('47', '1', 'admin', 'admin', 'login', '2025-05-31 03:45:12', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-30 19:45:12'),
('48', '1', 'admin', 'admin', 'login', '2025-05-31 20:29:36', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-31 12:29:36');

-- --------------------------------------------------------
-- Table structure for table `users`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `role` enum('admin','supplier','store_clerk','cashier') NOT NULL DEFAULT 'cashier',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_username` (`username`),
  KEY `idx_email` (`email`),
  KEY `idx_role` (`role`),
  KEY `idx_user_role_created` (`role`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table `users`
-- --------------------------------------------------------

INSERT INTO `users` VALUES
('1', 'admin', '$2y$12$NgCQRPEOw52hKpnE7/9tXufx43Bz7JvRiP/UtUffXMJsrwknYOYt6', 'admin@inventory.com', 'System Administrator', 'admin', '2025-05-25 14:27:47', '2025-05-25 16:16:36'),
('3', 'carlotzy', '$2y$12$WEzPjthOeapKAQ4u9a9pFub4e2kCCrCg1s63v8dJ843e4ywP7Pi7O', 'carlo7@gmail.com', 'Mary Store Clerk', 'cashier', '2025-05-25 14:27:47', '2025-05-27 22:21:29'),
('4', 'cashier1', '$2y$12$AAM2r2Tjz9OfHFCZ2xbbm.xyt4vnJn2vDvXqa.Hg2kQyxa1cJh.uW', 'cashier@inventory.com', 'Bob Cashier', 'cashier', '2025-05-25 14:27:47', '2025-05-26 11:09:57'),
('5', 'manager1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager@inventory.com', 'Alice Manager', 'admin', '2025-05-25 14:27:47', '2025-05-25 14:27:47'),
('9', 'carlo3', '$2y$12$0sqQ1u/VBaIYnBGmwGCuWuM3G9PP2kDahoYI9hkOfAmu3n.OtfVI.', 'carlo1@gmail.com', 'carlo', 'store_clerk', '2025-05-25 17:04:49', '2025-05-26 11:11:14');

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
