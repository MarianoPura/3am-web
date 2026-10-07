-- Explicit deployment SQL; no application request runs DDL.
-- Review on the intended database, back it up, and apply before deploying.
-- Run ALTER only if this column is absent (check information_schema.columns).
ALTER TABLE rental_items ADD COLUMN additional_image_paths JSON NULL;

CREATE TABLE IF NOT EXISTS rental_password_resets (
    user_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL DEFAULT NULL,
    reset_at DATETIME NULL DEFAULT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (user_id),
    UNIQUE KEY uq_rental_reset_token (token_hash),
    KEY idx_rental_reset_expiry (expires_at),
    CONSTRAINT fk_rental_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Requires the existing rental_service_requests table. Check each new column
-- in information_schema.columns first; do not re-run applied ALTER clauses.
-- Existing statuses/requests are preserved. No equipment/order financial data changes.
ALTER TABLE rental_service_requests
    ADD COLUMN quote_amount DECIMAL(12,2) NULL,
    ADD COLUMN quote_notes VARCHAR(2000) NULL,
    ADD COLUMN quote_version INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN payment_method_id BIGINT UNSIGNED NULL,
    ADD COLUMN payment_reference VARCHAR(190) NULL,
    ADD COLUMN payment_proof_path VARCHAR(255) NULL,
    ADD COLUMN payment_status VARCHAR(10) NOT NULL DEFAULT 'unpaid',
    ADD COLUMN payment_reviewed_by BIGINT UNSIGNED NULL,
    ADD COLUMN payment_reviewed_at TIMESTAMP NULL DEFAULT NULL,
    ADD COLUMN payment_message VARCHAR(1000) NULL,
    ADD COLUMN completed_at TIMESTAMP NULL DEFAULT NULL,
    ADD COLUMN cancelled_at TIMESTAMP NULL DEFAULT NULL,
    ADD KEY idx_service_payment_method (payment_method_id),
    ADD KEY idx_service_payment_reviewer (payment_reviewed_by),
    ADD CONSTRAINT fk_service_payment_method FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id),
    ADD CONSTRAINT fk_service_payment_reviewer FOREIGN KEY (payment_reviewed_by) REFERENCES users(id) ON DELETE SET NULL;

-- Service emails reuse the existing delivery ledger; they are not equipment orders.
ALTER TABLE rental_notification_deliveries
    ADD COLUMN service_request_id BIGINT UNSIGNED NULL,
    ADD KEY idx_notification_service (service_request_id, event_key),
    ADD CONSTRAINT fk_notification_service FOREIGN KEY (service_request_id) REFERENCES rental_service_requests(id) ON DELETE SET NULL;
