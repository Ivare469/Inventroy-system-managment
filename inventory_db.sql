-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 02, 2026 at 06:58 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `inventory_db`
--
CREATE DATABASE IF NOT EXISTS `inventory_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `inventory_db`;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `Category_name` varchar(50) NOT NULL,
  `Category_id` int(11) NOT NULL,
  `Description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`Category_name`, `Category_id`, `Description`) VALUES
('Audio Gear', 104, 'Wireless earbuds and noise-canceling headphones.'),
('Gaming Consoles', 102, 'Home and portable gaming systems.'),
('Smartphones', 101, 'Mobile devices and handheld communication.'),
('Wearables', 103, 'Smartwatches and fitness trackers.');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `Product_id` int(11) NOT NULL,
  `product_name` varchar(100) DEFAULT NULL,
  `Category_name` varchar(50) DEFAULT NULL,
  `Supplier_id` int(11) DEFAULT NULL,
  `Unit_price` decimal(10,2) DEFAULT NULL,
  `Stock_qty` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`Product_id`, `product_name`, `Category_name`, `Supplier_id`, `Unit_price`, `Stock_qty`) VALUES
(0, 'Xiaomi Redmi Note 10 Pro', 'Smartphones', 501, 12599.00, 67),
(3001, 'POCO X7 Pro 5G', 'Smartphones', 501, 18500.00, 25),
(3002, 'PlayStation 5 Slim', 'Gaming Consoles', 502, 30500.00, 15),
(3003, 'Apple Watch Series 9', 'Wearables', 503, 24900.00, 10),
(3004, 'Galaxy Buds3 Pro', 'Audio Gear', 504, 12500.00, 35),
(3005, 'Nintendo Switch OLED', 'Gaming Consoles', 502, 16800.00, 12);

-- --------------------------------------------------------

--
-- Table structure for table `stock_transactions`
--

DROP TABLE IF EXISTS `stock_transactions`;
CREATE TABLE `stock_transactions` (
  `Transaction_id` int(11) NOT NULL,
  `Product_id` int(11) DEFAULT NULL,
  `Transaction_qty` int(11) DEFAULT NULL,
  `Transaction_type` varchar(20) DEFAULT NULL,
  `transaction_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

DROP TABLE IF EXISTS `suppliers`;
CREATE TABLE `suppliers` (
  `Supplier_id` int(11) NOT NULL,
  `supplier_name` varchar(100) DEFAULT NULL,
  `supply_contact` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`Supplier_id`, `supplier_name`, `supply_contact`) VALUES
(501, 'Xiaomi Philippines', 'Kenji Sy'),
(502, 'Sony Interactive Ent.', 'Haruto Tanaka'),
(503, 'Apple Distribution', 'Sarah Miller'),
(504, 'Samsung Mobile Ph', 'Min-Ho Lee');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`Category_name`),
  ADD UNIQUE KEY `Category_id` (`Category_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`Product_id`),
  ADD KEY `Category_name` (`Category_name`),
  ADD KEY `Supplier_id` (`Supplier_id`);

--
-- Indexes for table `stock_transactions`
--
ALTER TABLE `stock_transactions`
  ADD PRIMARY KEY (`Transaction_id`),
  ADD KEY `Product_id` (`Product_id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`Supplier_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `Category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=105;

--
-- AUTO_INCREMENT for table `stock_transactions`
--
ALTER TABLE `stock_transactions`
  MODIFY `Transaction_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`Category_name`) REFERENCES `categories` (`Category_name`),
  ADD CONSTRAINT `products_ibfk_2` FOREIGN KEY (`Supplier_id`) REFERENCES `suppliers` (`Supplier_id`);

--
-- Constraints for table `stock_transactions`
--
ALTER TABLE `stock_transactions`
  ADD CONSTRAINT `stock_transactions_ibfk_1` FOREIGN KEY (`Product_id`) REFERENCES `products` (`Product_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
