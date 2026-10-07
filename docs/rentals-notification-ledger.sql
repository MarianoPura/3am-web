-- Review/apply only if this table is absent. Never replaces an existing ledger.
-- No editable mailer settings/recipients table is required by current code.
CREATE TABLE IF NOT EXISTS rental_notification_deliveries (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 dedup_key CHAR(64) NOT NULL,
 event_key VARCHAR(40) NOT NULL,
 event_version CHAR(64) NULL,
 order_id BIGINT UNSIGNED NULL,
 service_request_id BIGINT UNSIGNED NULL,
 audience VARCHAR(20) NOT NULL,
 recipient_email VARCHAR(190) NOT NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'pending',
 attempts INT UNSIGNED NOT NULL DEFAULT 0,
 failure_category VARCHAR(40) NULL,
 attempted_at TIMESTAMP NULL,
 sent_at TIMESTAMP NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY rental_notification_deliveries_dedup_unique (dedup_key),
 KEY rental_notification_deliveries_status_index (status,created_at),
 KEY rental_notification_deliveries_order_index (order_id,event_key),
 KEY idx_notification_service (service_request_id,event_key),
 CONSTRAINT rental_notification_deliveries_order_fk FOREIGN KEY (order_id) REFERENCES order_header(id) ON DELETE SET NULL,
 CONSTRAINT fk_notification_service FOREIGN KEY (service_request_id) REFERENCES rental_service_requests(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
