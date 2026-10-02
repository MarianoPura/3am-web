-- Additive transactional mailer schema. Review before running on LIVE.
-- No existing order/customer data is changed. MySQL DDL commits implicitly.
-- CREATE IF NOT EXISTS does not repair incompatible pre-existing tables.
CREATE TABLE IF NOT EXISTS `rental_email_templates` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_key` varchar(40) NOT NULL,
  `audience` varchar(20) NOT NULL,
  `display_name` varchar(150) NOT NULL,
  `subject` varchar(200) NOT NULL,
  `body` text NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `rental_email_templates_event_audience_unique` (`event_key`, `audience`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rental_notification_recipients` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `email` varchar(190) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `rental_notification_recipients_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rental_notification_recipient_events` (
  `recipient_id` bigint(20) UNSIGNED NOT NULL,
  `event_key` varchar(40) NOT NULL,
  PRIMARY KEY (`recipient_id`, `event_key`),
  KEY `rental_notification_events_lookup` (`event_key`, `recipient_id`),
  CONSTRAINT `rental_notification_events_recipient_fk` FOREIGN KEY (`recipient_id`)
    REFERENCES `rental_notification_recipients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rental_notification_deliveries` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `dedup_key` char(64) NOT NULL,
  `event_key` varchar(40) NOT NULL,
  `event_version` char(64) DEFAULT NULL,
  `order_id` bigint(20) UNSIGNED DEFAULT NULL,
  `audience` varchar(20) NOT NULL,
  `recipient_id` bigint(20) UNSIGNED DEFAULT NULL,
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
  KEY `rental_notification_deliveries_recipient_index` (`recipient_id`),
  CONSTRAINT `rental_notification_deliveries_order_fk` FOREIGN KEY (`order_id`)
    REFERENCES `order_header` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `rental_notification_deliveries_recipient_fk` FOREIGN KEY (`recipient_id`)
    REFERENCES `rental_notification_recipients` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
