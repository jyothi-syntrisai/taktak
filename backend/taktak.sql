-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Aug 14, 2026 at 09:31 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.3.29

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `taktak`
--

-- --------------------------------------------------------

--
-- Table structure for table `brands`
--

CREATE TABLE `brands` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `code` varchar(50) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `distributors`
--

CREATE TABLE `distributors` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(200) NOT NULL,
  `code` varchar(50) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `distributor_regions`
--

CREATE TABLE `distributor_regions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `distributor_id` bigint(20) UNSIGNED NOT NULL,
  `region_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `import_batches`
--

CREATE TABLE `import_batches` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `module` enum('products','product_mrp') NOT NULL,
  `total_records` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `success_records` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `failed_records` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `status` enum('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `version` varchar(255) NOT NULL,
  `class` varchar(255) NOT NULL,
  `group` varchar(255) NOT NULL,
  `namespace` varchar(255) NOT NULL,
  `time` int(11) NOT NULL,
  `batch` int(11) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `version`, `class`, `group`, `namespace`, `time`, `batch`) VALUES
(1, '2026-01-01-000000', 'App\\Database\\Migrations\\CreateTaktakSchema', 'default', 'App', 1786684145, 1),
(2, '2026-01-02-000000', 'App\\Database\\Migrations\\AddUserRegionAndState', 'default', 'App', 1786699157, 2);

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `slug` varchar(100) NOT NULL,
  `module` varchar(50) NOT NULL,
  `action` varchar(50) NOT NULL,
  `name` varchar(150) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`id`, `slug`, `module`, `action`, `name`, `status`, `created_at`, `created_by`, `updated_at`, `updated_by`) VALUES
(1, 'users:view', 'users', 'view', 'View Users', 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(2, 'users:create', 'users', 'create', 'Create Users', 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(3, 'users:edit', 'users', 'edit', 'Edit Users', 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(4, 'users:delete', 'users', 'delete', 'Delete Users', 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(5, 'roles:view', 'roles', 'view', 'View Roles & Permissions', 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(6, 'roles:create', 'roles', 'create', 'Create Roles & Permissions', 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(7, 'roles:edit', 'roles', 'edit', 'Edit Roles & Permissions', 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(8, 'roles:delete', 'roles', 'delete', 'Delete Roles & Permissions', 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(9, 'roles:assign', 'roles', 'assign', 'Assign Roles & Permissions', 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(10, 'regions:view', 'regions', 'view', 'View Regions', 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(11, 'regions:create', 'regions', 'create', 'Create Regions', 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(12, 'regions:edit', 'regions', 'edit', 'Edit Regions', 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(13, 'regions:delete', 'regions', 'delete', 'Delete Regions', 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(14, 'distributors:view', 'distributors', 'view', 'View Distributors', 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(15, 'distributors:create', 'distributors', 'create', 'Create Distributors', 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(16, 'distributors:edit', 'distributors', 'edit', 'Edit Distributors', 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(17, 'distributors:delete', 'distributors', 'delete', 'Delete Distributors', 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(18, 'brands:view', 'brands', 'view', 'View Brands', 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(19, 'brands:create', 'brands', 'create', 'Create Brands', 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(20, 'brands:edit', 'brands', 'edit', 'Edit Brands', 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(21, 'brands:delete', 'brands', 'delete', 'Delete Brands', 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(22, 'products:view', 'products', 'view', 'View Products', 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(23, 'products:create', 'products', 'create', 'Create Products', 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(24, 'products:edit', 'products', 'edit', 'Edit Products', 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(25, 'products:delete', 'products', 'delete', 'Delete Products', 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(26, 'products:import', 'products', 'import', 'Import Products', 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(27, 'product_mrp:view', 'product_mrp', 'view', 'View Product MRP', 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(28, 'product_mrp:create', 'product_mrp', 'create', 'Create Product MRP', 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(29, 'product_mrp:import', 'product_mrp', 'import', 'Import Product MRP', 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(30, 'imports:view', 'imports', 'view', 'View CSV Imports', 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `brand_id` bigint(20) UNSIGNED NOT NULL,
  `sku` varchar(100) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_import_staging`
--

CREATE TABLE `product_import_staging` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `import_batch_id` bigint(20) UNSIGNED NOT NULL,
  `row_number` int(10) UNSIGNED NOT NULL,
  `brand_name` varchar(150) DEFAULT NULL,
  `sku` varchar(100) DEFAULT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `mrp` varchar(50) DEFAULT NULL,
  `status` enum('pending','valid','error','processed') NOT NULL DEFAULT 'pending',
  `error_message` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_mrp`
--

CREATE TABLE `product_mrp` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `mrp` decimal(12,2) NOT NULL,
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `refresh_tokens`
--

CREATE TABLE `refresh_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `ip_address` varchar(64) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `refresh_tokens`
--

INSERT INTO `refresh_tokens` (`id`, `user_id`, `token_hash`, `expires_at`, `revoked_at`, `user_agent`, `ip_address`, `status`, `created_at`, `created_by`, `updated_at`, `updated_by`) VALUES
(1, 1, 'a51e3800073905010826f4007795d030806bce96c191bd3c0969d28da6eff143', '2026-08-20 08:44:29', NULL, 'node', '::ffff:127.0.0.1', 'active', '2026-08-13 08:44:29', 1, '2026-08-13 08:44:29', 1),
(2, 4, '29e97530358eecccaa02a0ae5c5ab50991d0bce3b83e5c307d5396303b22ee42', '2026-08-20 08:44:29', NULL, 'node', '::ffff:127.0.0.1', 'active', '2026-08-13 08:44:29', 4, '2026-08-13 08:44:29', 4),
(3, 1, '386838f9bd6dab5a961584f0cc0b5f167207714eb3a1c089b63c00e6e35b778d', '2026-08-20 08:57:01', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '::1', 'active', '2026-08-13 08:57:01', 1, '2026-08-13 08:57:01', 1),
(4, 1, 'e9b09e479d75cb1f0121b455ee8e24538850b27270545b130ed6e4d5cabb6539', '2026-08-21 06:29:27', NULL, 'curl/8.17.0', '127.0.0.1', 'active', '2026-08-14 06:29:27', 1, '2026-08-14 06:29:27', 1),
(5, 1, '843ec067802d8a6eb4bc64e868a9d6e769444416630f48584ea679cf8c101b20', '2026-08-21 06:38:15', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '::1', 'active', '2026-08-14 06:38:15', 1, '2026-08-14 06:38:15', 1),
(6, 1, '77c64039dad6cd4cdc45c22c124c59b2520b50d3846d27a86c9987ec461ee7f8', '2026-08-21 09:19:34', NULL, 'curl/8.17.0', '::1', 'active', '2026-08-14 09:19:34', 1, '2026-08-14 09:19:34', 1);

-- --------------------------------------------------------

--
-- Table structure for table `regions`
--

CREATE TABLE `regions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `region_states`
--

CREATE TABLE `region_states` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `region_id` bigint(20) UNSIGNED NOT NULL,
  `state_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `description`, `is_system`, `status`, `created_at`, `created_by`, `updated_at`, `updated_by`) VALUES
(1, 'SUPER_ADMIN', 'Full access to everything, including roles and permissions', 1, 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(2, 'ADMIN', 'Manages masters, products and imports', 1, 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(3, 'SALES_PERSON', 'Read-only access to masters and products', 1, 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(4, 'RSM', 'Regional Sales Manager - works within one region', 1, 'active', '2026-08-14 09:19:17', NULL, '2026-08-14 09:19:17', NULL),
(5, 'SO', 'Sales Officer - works within one state of one region', 1, 'active', '2026-08-14 09:19:17', NULL, '2026-08-14 09:19:17', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `role_id` bigint(20) UNSIGNED NOT NULL,
  `permission_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `role_permissions`
--

INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`, `created_by`, `updated_at`, `updated_by`) VALUES
(1, 1, 1, '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(2, 1, 2, '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(3, 1, 3, '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(4, 1, 4, '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(5, 1, 5, '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(6, 1, 6, '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(7, 1, 7, '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(8, 1, 8, '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(9, 1, 9, '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(10, 1, 10, '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(11, 1, 11, '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(12, 1, 12, '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(13, 1, 13, '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(14, 1, 14, '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(15, 1, 15, '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(16, 1, 16, '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(17, 1, 17, '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(18, 1, 18, '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(19, 1, 19, '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(20, 1, 20, '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(21, 1, 21, '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(22, 1, 22, '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(23, 1, 23, '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(24, 1, 24, '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(25, 1, 25, '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(26, 1, 26, '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(27, 1, 27, '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(28, 1, 28, '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(29, 1, 29, '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(30, 1, 30, '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(31, 2, 1, '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(32, 2, 2, '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(33, 2, 3, '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(34, 2, 10, '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(35, 2, 11, '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(36, 2, 12, '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(37, 2, 13, '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(38, 2, 14, '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(39, 2, 15, '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(40, 2, 16, '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(41, 2, 17, '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(42, 2, 18, '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(43, 2, 19, '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(44, 2, 20, '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(45, 2, 21, '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(46, 2, 22, '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(47, 2, 23, '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(48, 2, 24, '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(49, 2, 25, '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(50, 2, 26, '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(51, 2, 27, '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(52, 2, 28, '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(53, 2, 29, '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(54, 2, 30, '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(55, 3, 10, '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(56, 3, 14, '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(57, 3, 18, '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(58, 3, 22, '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(59, 3, 27, '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(60, 3, 30, '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(61, 4, 10, '2026-08-14 09:19:17', NULL, '2026-08-14 09:19:17', NULL),
(62, 4, 14, '2026-08-14 09:19:17', NULL, '2026-08-14 09:19:17', NULL),
(63, 4, 18, '2026-08-14 09:19:17', NULL, '2026-08-14 09:19:17', NULL),
(64, 4, 22, '2026-08-14 09:19:17', NULL, '2026-08-14 09:19:17', NULL),
(65, 4, 27, '2026-08-14 09:19:17', NULL, '2026-08-14 09:19:17', NULL),
(66, 4, 30, '2026-08-14 09:19:17', NULL, '2026-08-14 09:19:17', NULL),
(67, 5, 10, '2026-08-14 09:19:17', NULL, '2026-08-14 09:19:17', NULL),
(68, 5, 14, '2026-08-14 09:19:17', NULL, '2026-08-14 09:19:17', NULL),
(69, 5, 18, '2026-08-14 09:19:17', NULL, '2026-08-14 09:19:17', NULL),
(70, 5, 22, '2026-08-14 09:19:17', NULL, '2026-08-14 09:19:17', NULL),
(71, 5, 27, '2026-08-14 09:19:17', NULL, '2026-08-14 09:19:17', NULL),
(72, 5, 30, '2026-08-14 09:19:17', NULL, '2026-08-14 09:19:17', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `states`
--

CREATE TABLE `states` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(10) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `states`
--

INSERT INTO `states` (`id`, `name`, `code`, `status`, `created_at`, `created_by`, `updated_at`, `updated_by`) VALUES
(1, 'Andhra Pradesh', 'AP', 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(2, 'Arunachal Pradesh', 'AR', 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(3, 'Assam', 'AS', 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(4, 'Bihar', 'BR', 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(5, 'Chhattisgarh', 'CG', 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(6, 'Goa', 'GA', 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(7, 'Gujarat', 'GJ', 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(8, 'Haryana', 'HR', 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(9, 'Himachal Pradesh', 'HP', 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(10, 'Jharkhand', 'JH', 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(11, 'Karnataka', 'KA', 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(12, 'Kerala', 'KL', 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(13, 'Madhya Pradesh', 'MP', 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(14, 'Maharashtra', 'MH', 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(15, 'Manipur', 'MN', 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(16, 'Meghalaya', 'ML', 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(17, 'Mizoram', 'MZ', 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(18, 'Nagaland', 'NL', 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(19, 'Odisha', 'OD', 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(20, 'Punjab', 'PB', 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(21, 'Rajasthan', 'RJ', 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(22, 'Sikkim', 'SK', 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(23, 'Tamil Nadu', 'TN', 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(24, 'Telangana', 'TS', 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(25, 'Tripura', 'TR', 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(26, 'Uttar Pradesh', 'UP', 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(27, 'Uttarakhand', 'UK', 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(28, 'West Bengal', 'WB', 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(29, 'Andaman and Nicobar Islands', 'AN', 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(30, 'Chandigarh', 'CH', 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(31, 'Dadra and Nagar Haveli and Daman and Diu', 'DH', 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(32, 'Delhi', 'DL', 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(33, 'Jammu and Kashmir', 'JK', 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(34, 'Ladakh', 'LA', 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(35, 'Lakshadweep', 'LD', 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL),
(36, 'Puducherry', 'PY', 'active', '2026-08-13 07:35:27', NULL, '2026-08-13 07:35:27', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role_id` bigint(20) UNSIGNED NOT NULL,
  `region_id` bigint(20) UNSIGNED DEFAULT NULL,
  `state_id` bigint(20) UNSIGNED DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `last_login_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `password_hash`, `role_id`, `region_id`, `state_id`, `status`, `last_login_at`, `created_at`, `created_by`, `updated_at`, `updated_by`) VALUES
(1, 'Super Admin', 'superadmin@taktak.com', '$2b$12$UhDYX1GWfL2bKHKUFCOoZ.5R0wFAnuiHbc7K3AW.3FOy7okSr9TKm', 1, NULL, NULL, 'active', '2026-08-14 09:19:34', '2026-08-13 07:35:27', 1, '2026-08-14 09:19:34', 1),
(4, 'Test Admin', 'admin@taktak.com', '$2b$12$tmud2zcLASB8drCNr5AuEOsC9LRNeFQiRmqKlSkHlsWyf0eZTf/ly', 2, NULL, NULL, 'active', '2026-08-13 08:44:29', '2026-08-13 08:44:29', 1, '2026-08-13 08:44:29', 1);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_current_price_list`
-- (See below for the actual view)
--
CREATE TABLE `v_current_price_list` (
`product_id` bigint(20) unsigned
,`sku` varchar(100)
,`product_name` varchar(255)
,`brand_name` varchar(150)
,`mrp` decimal(12,2)
,`effective_from` date
);

-- --------------------------------------------------------

--
-- Structure for view `v_current_price_list`
--
DROP TABLE IF EXISTS `v_current_price_list`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_current_price_list`  AS SELECT `p`.`id` AS `product_id`, `p`.`sku` AS `sku`, `p`.`product_name` AS `product_name`, `b`.`name` AS `brand_name`, `m`.`mrp` AS `mrp`, `m`.`effective_from` AS `effective_from` FROM ((`products` `p` join `brands` `b` on(`b`.`id` = `p`.`brand_id`)) left join `product_mrp` `m` on(`m`.`product_id` = `p`.`id` and `m`.`effective_to` is null)) WHERE `p`.`status` = 'active' ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `brands`
--
ALTER TABLE `brands`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_brands_name` (`name`),
  ADD UNIQUE KEY `uq_brands_code` (`code`);

--
-- Indexes for table `distributors`
--
ALTER TABLE `distributors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_distributors_code` (`code`);

--
-- Indexes for table `distributor_regions`
--
ALTER TABLE `distributor_regions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_distributor_region` (`distributor_id`,`region_id`),
  ADD KEY `fk_dr_region` (`region_id`);

--
-- Indexes for table `import_batches`
--
ALTER TABLE `import_batches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_permissions_slug` (`slug`),
  ADD UNIQUE KEY `uq_permissions_module_action` (`module`,`action`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_products_sku` (`sku`),
  ADD KEY `idx_products_brand_status` (`brand_id`,`status`);

--
-- Indexes for table `product_import_staging`
--
ALTER TABLE `product_import_staging`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_staging_batch_status` (`import_batch_id`,`status`);

--
-- Indexes for table `product_mrp`
--
ALTER TABLE `product_mrp`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_mrp_product_from` (`product_id`,`effective_from`);

--
-- Indexes for table `refresh_tokens`
--
ALTER TABLE `refresh_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_refresh_token_hash` (`token_hash`),
  ADD KEY `idx_refresh_user` (`user_id`,`revoked_at`);

--
-- Indexes for table `regions`
--
ALTER TABLE `regions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_regions_name` (`name`);

--
-- Indexes for table `region_states`
--
ALTER TABLE `region_states`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_region_state` (`region_id`,`state_id`),
  ADD KEY `fk_rs_state` (`state_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_roles_name` (`name`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_role_permission` (`role_id`,`permission_id`),
  ADD KEY `fk_rp_permission` (`permission_id`);

--
-- Indexes for table `states`
--
ALTER TABLE `states`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_states_name` (`name`),
  ADD UNIQUE KEY `uq_states_code` (`code`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_email` (`email`),
  ADD KEY `idx_users_role` (`role_id`),
  ADD KEY `idx_users_region` (`region_id`),
  ADD KEY `idx_users_state` (`state_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `brands`
--
ALTER TABLE `brands`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `distributors`
--
ALTER TABLE `distributors`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `distributor_regions`
--
ALTER TABLE `distributor_regions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `import_batches`
--
ALTER TABLE `import_batches`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_import_staging`
--
ALTER TABLE `product_import_staging`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_mrp`
--
ALTER TABLE `product_mrp`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `refresh_tokens`
--
ALTER TABLE `refresh_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `regions`
--
ALTER TABLE `regions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `region_states`
--
ALTER TABLE `region_states`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `role_permissions`
--
ALTER TABLE `role_permissions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=73;

--
-- AUTO_INCREMENT for table `states`
--
ALTER TABLE `states`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `distributor_regions`
--
ALTER TABLE `distributor_regions`
  ADD CONSTRAINT `fk_dr_distributor` FOREIGN KEY (`distributor_id`) REFERENCES `distributors` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_dr_region` FOREIGN KEY (`region_id`) REFERENCES `regions` (`id`);

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_products_brand` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`);

--
-- Constraints for table `product_import_staging`
--
ALTER TABLE `product_import_staging`
  ADD CONSTRAINT `fk_staging_batch` FOREIGN KEY (`import_batch_id`) REFERENCES `import_batches` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `product_mrp`
--
ALTER TABLE `product_mrp`
  ADD CONSTRAINT `fk_mrp_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `refresh_tokens`
--
ALTER TABLE `refresh_tokens`
  ADD CONSTRAINT `fk_refresh_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `region_states`
--
ALTER TABLE `region_states`
  ADD CONSTRAINT `fk_rs_region` FOREIGN KEY (`region_id`) REFERENCES `regions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_rs_state` FOREIGN KEY (`state_id`) REFERENCES `states` (`id`);

--
-- Constraints for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `fk_rp_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`),
  ADD CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_region` FOREIGN KEY (`region_id`) REFERENCES `regions` (`id`),
  ADD CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`),
  ADD CONSTRAINT `fk_users_state` FOREIGN KEY (`state_id`) REFERENCES `states` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
