-- Additive fixed-mailer delivery ledger only. Review before running on LIVE.
-- No editable template/recipient tables are needed.
-- Existing tables from the previous editable version may remain unused.
-- No existing business records or mail history are changed.
-- CREATE IF NOT EXISTS does not repair incompatible pre-existing tables.
CREATE TABLE IF NOT EXISTS `rental_notification_deliveries` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `dedup_key` char(64) NOT NULL,
  `event_key` varchar(40) NOT NULL,
  `event_version` char(64) DEFAULT NULL,
  `order_id` bigint(20) UNSIGNED DEFAULT NULL,
  `audience` varchar(20) NOT NULL,
  `recipient_email` varchar(190) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `attempts` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `failure_category` varchar(40) DEFAULT NULL,
  `attempted_at` timestamp NULL DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `rental_notification_deliveries_dedup_unique` (`dedup_key`),
  KEY `rental_notification_deliveries_status_index` (`status`, `created_at`),
  KEY `rental_notification_deliveries_order_index` (`order_id`, `event_key`),
  CONSTRAINT `rental_notification_deliveries_order_fk` FOREIGN KEY (`order_id`)
    REFERENCES `order_header` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
