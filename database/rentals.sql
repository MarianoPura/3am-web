-- Rentals schema. No records, credentials or environment names.
-- Use bin/rentals.php --check first. Existing incompatible schemas require a reviewed migration.
-- DDL commits implicitly in MySQL/MariaDB; this is not a transactional upgrade.

CREATE TABLE IF NOT EXISTS `users` (
`id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `email` varchar(190) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'customer',
  `two_factor_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `totp_secret` varchar(255) DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rental_categories` (
`id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `slug` varchar(150) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `image_path` varchar(500) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
PRIMARY KEY (`id`),
  UNIQUE KEY `rental_categories_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rental_items` (
`id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(190) NOT NULL,
  `slug` varchar(190) DEFAULT NULL,
  `sku` varchar(80) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ideal_use` varchar(500) DEFAULT NULL,
  `image_path` varchar(500) DEFAULT NULL,
  `is_service` tinyint(1) NOT NULL DEFAULT 0,
  `availability_status` varchar(50) NOT NULL DEFAULT 'available',
  `rental_unit` varchar(30) DEFAULT NULL,
  `rental_rate` decimal(12,2) NOT NULL DEFAULT 0.00,
  `security_deposit` decimal(12,2) DEFAULT 0.00,
  `available_quantity` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
PRIMARY KEY (`id`),
  UNIQUE KEY `rental_items_slug_unique` (`slug`),
  UNIQUE KEY `rental_items_sku_unique` (`sku`),
  KEY `rental_items_category_index` (`category_id`),
  KEY `rental_items_service_active_index` (`is_service`, `is_active`, `category_id`),
  KEY `rental_items_availability_index` (`availability_status`, `is_active`),
CONSTRAINT `rental_items_category_fk` FOREIGN KEY (`category_id`) REFERENCES `rental_categories` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rental_item_blackouts` (
`id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `rental_item_id` bigint(20) UNSIGNED NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
PRIMARY KEY (`id`),
  KEY `rental_item_blackouts_item_dates_index` (`rental_item_id`, `start_date`, `end_date`, `is_active`),
CONSTRAINT `rental_item_blackouts_item_fk` FOREIGN KEY (`rental_item_id`) REFERENCES `rental_items` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `carts` (
`id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
PRIMARY KEY (`id`),
  KEY `carts_user_index` (`user_id`),
CONSTRAINT `carts_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cart_items` (
`id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `cart_id` bigint(20) UNSIGNED NOT NULL,
  `rental_item_id` bigint(20) UNSIGNED NOT NULL,
  `quantity` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `rental_start_date` date DEFAULT NULL,
  `rental_end_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
PRIMARY KEY (`id`),
  KEY `cart_items_cart_index` (`cart_id`),
  KEY `cart_items_rental_item_index` (`rental_item_id`),
CONSTRAINT `cart_items_cart_fk` FOREIGN KEY (`cart_id`) REFERENCES `carts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `cart_items_rental_item_fk` FOREIGN KEY (`rental_item_id`) REFERENCES `rental_items` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payment_methods` (
`id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `type` varchar(30) NOT NULL,
  `account_name` varchar(190) DEFAULT NULL,
  `account_number` varchar(100) DEFAULT NULL,
  `provider` varchar(120) DEFAULT NULL,
  `qr_image_path` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `order_header` (
`id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_number` varchar(50) NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `customer_name` varchar(190) NOT NULL,
  `customer_email` varchar(190) NOT NULL,
  `customer_phone` varchar(40) DEFAULT NULL,
  `payment_method_id` bigint(20) UNSIGNED DEFAULT NULL,
  `payment_status` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `payment_reference` varchar(190) DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `payment_proof_path` varchar(255) DEFAULT NULL,
  `payment_reviewed_at` timestamp NULL DEFAULT NULL,
  `payment_reviewed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `status_token` varchar(64) DEFAULT NULL,
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `security_deposit` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
PRIMARY KEY (`id`),
  UNIQUE KEY `order_header_order_number_unique` (`order_number`),
  UNIQUE KEY `order_header_status_token_unique` (`status_token`),
  KEY `order_header_user_index` (`user_id`),
  KEY `order_header_payment_method_index` (`payment_method_id`),
  KEY `order_header_payment_date_index` (`payment_status`, `created_at`),
  KEY `order_header_payment_status_index` (`payment_status`),
CONSTRAINT `order_header_payment_method_fk` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `order_header_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `order_details` (
`id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_header_id` bigint(20) UNSIGNED NOT NULL,
  `rental_item_id` bigint(20) UNSIGNED NOT NULL,
  `item_name` varchar(190) NOT NULL,
  `quantity` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `rental_start_date` date DEFAULT NULL,
  `rental_end_date` date DEFAULT NULL,
  `unit_rate` decimal(12,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
PRIMARY KEY (`id`),
  KEY `order_details_header_index` (`order_header_id`),
  KEY `order_details_rental_item_index` (`rental_item_id`),
  KEY `order_details_item_dates_index` (`rental_item_id`, `rental_start_date`, `rental_end_date`),
CONSTRAINT `order_details_header_fk` FOREIGN KEY (`order_header_id`) REFERENCES `order_header` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `order_details_rental_item_fk` FOREIGN KEY (`rental_item_id`) REFERENCES `rental_items` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
