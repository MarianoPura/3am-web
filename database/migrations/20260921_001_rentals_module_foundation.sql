-- 3AM Rentals - Simple Standard Database Migration
-- Target: MariaDB / MySQL
-- IMPORTANT:
-- 1) This assumes the OLD complicated foundation migration was NOT executed.
-- 2) Back up the database before applying.
-- 3) Update app/Models/RentalCatalog.php to the matching version before/with this migration.
-- 4) No e-commerce orders/payments tables are created.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL DEFAULT 'customer',
    two_factor_enabled BOOLEAN NOT NULL DEFAULT FALSE,
    totp_secret VARCHAR(255) NULL,
    last_login TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX user_sessions_user_id (user_id),
    CONSTRAINT user_sessions_user_fk
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE rental_items
    MODIFY COLUMN slug VARCHAR(190) NULL;
    ADD COLUMN IF NOT EXISTS description TEXT NULL AFTER slug,
    ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER is_active,
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP AFTER created_at,
    DROP COLUMN IF EXISTS display_order;

ALTER TABLE rental_items
    DROP INDEX rental_visibility,
    MODIFY COLUMN slug VARCHAR(190) NULL,
    CHANGE COLUMN media_reference image_reference VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS rental_rate DECIMAL(12,2) NULL AFTER availability_status,
    ADD COLUMN IF NOT EXISTS security_deposit DECIMAL(12,2) NULL AFTER rental_rate,
    ADD COLUMN IF NOT EXISTS available_quantity INT UNSIGNED NOT NULL DEFAULT 0 AFTER security_deposit,
    DROP COLUMN IF EXISTS display_order,
    ADD INDEX rental_items_active (is_active),
    ADD INDEX rental_items_category (category_id);

ALTER TABLE rental_inclusions
    DROP INDEX rental_inclusion_order,
    DROP COLUMN display_order,
    ADD INDEX rental_inclusions_item (rental_item_id);

ALTER TABLE rental_inclusions
    DROP FOREIGN KEY rental_inclusion_fk,
    ADD CONSTRAINT rental_inclusion_fk
        FOREIGN KEY (rental_item_id)
        REFERENCES rental_items(id)
        ON DELETE RESTRICT;

CREATE TABLE IF NOT EXISTS rental_services (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(190) NOT NULL,
    slug VARCHAR(150) NULL UNIQUE,
    description TEXT NOT NULL,
    image_reference VARCHAR(255) NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rental_carts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX rental_carts_user_id (user_id),
    CONSTRAINT rental_carts_user_fk
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rental_cart_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rental_cart_id BIGINT UNSIGNED NOT NULL,
    rental_item_id BIGINT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    rental_start_date DATE NULL,
    rental_end_date DATE NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX rental_cart_items_cart (rental_cart_id),
    INDEX rental_cart_items_item (rental_item_id),
    CONSTRAINT rental_cart_items_cart_fk
        FOREIGN KEY (rental_cart_id) REFERENCES rental_carts(id) ON DELETE RESTRICT,
    CONSTRAINT rental_cart_items_item_fk
        FOREIGN KEY (rental_item_id) REFERENCES rental_items(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_methods (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    account_name VARCHAR(190) NULL,
    account_number VARCHAR(190) NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rental_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_number VARCHAR(50) NOT NULL UNIQUE,
    user_id BIGINT UNSIGNED NOT NULL,
    payment_method_id BIGINT UNSIGNED NULL,
    rental_start_date DATE NULL,
    rental_end_date DATE NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'pending',
    payment_status VARCHAR(50) NOT NULL DEFAULT 'unpaid',
    payment_proof_path VARCHAR(500) NULL,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    customer_notes TEXT NULL,
    admin_notes TEXT NULL,
    reviewed_by BIGINT UNSIGNED NULL,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX rental_requests_user (user_id),
    INDEX rental_requests_status (status),
    INDEX rental_requests_payment_status (payment_status),
    CONSTRAINT rental_requests_user_fk
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT rental_requests_payment_method_fk
        FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id) ON DELETE RESTRICT,
    CONSTRAINT rental_requests_reviewer_fk
        FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rental_request_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rental_request_id BIGINT UNSIGNED NOT NULL,
    rental_item_id BIGINT UNSIGNED NOT NULL,
    item_name VARCHAR(190) NOT NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    rental_rate DECIMAL(12,2) NULL,
    line_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    INDEX rental_request_items_request (rental_request_id),
    INDEX rental_request_items_item (rental_item_id),
    CONSTRAINT rental_request_items_request_fk
        FOREIGN KEY (rental_request_id) REFERENCES rental_requests(id) ON DELETE RESTRICT,
    CONSTRAINT rental_request_items_item_fk
        FOREIGN KEY (rental_item_id) REFERENCES rental_items(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS support_content (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(190) NOT NULL,
    slug VARCHAR(150) NULL UNIQUE,
    content TEXT NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
