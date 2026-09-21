CREATE TABLE IF NOT EXISTS admin_users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    display_name VARCHAR(160) NOT NULL,
    role VARCHAR(40) NOT NULL DEFAULT 'admin',
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    two_factor_enabled BOOLEAN NOT NULL DEFAULT FALSE,
    totp_secret_ciphertext TEXT NULL,
    last_login_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE INDEX admin_users_email_unique (email),
    INDEX admin_users_access (is_active, deleted_at)
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS customers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(40) NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    email_verified_at TIMESTAMP NULL DEFAULT NULL,
    last_login_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE INDEX customers_email_unique (email),
    INDEX customers_access (is_active, deleted_at),
    INDEX customers_created_at (created_at)
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS customer_addresses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT UNSIGNED NOT NULL,
    label VARCHAR(80) NULL,
    recipient_name VARCHAR(200) NOT NULL,
    phone VARCHAR(40) NULL,
    address_line_1 VARCHAR(255) NOT NULL,
    address_line_2 VARCHAR(255) NULL,
    locality VARCHAR(160) NOT NULL,
    region VARCHAR(160) NULL,
    postal_code VARCHAR(32) NULL,
    country_code CHAR(2) NOT NULL DEFAULT 'PH',
    is_default BOOLEAN NOT NULL DEFAULT FALSE,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,

    INDEX customer_addresses_listing (
        customer_id,
        is_active,
        deleted_at
    ),

    CONSTRAINT customer_addresses_customer_fk
        FOREIGN KEY (customer_id)
        REFERENCES customers(id)
        ON DELETE RESTRICT
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS rental_categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(120) NOT NULL,
    description TEXT NULL,
    image_reference VARCHAR(190) NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    display_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    deleted_by BIGINT UNSIGNED NULL,

    UNIQUE INDEX rental_categories_slug_unique (slug),

    INDEX rental_categories_listing (
        is_active,
        deleted_at,
        display_order
    ),

    CONSTRAINT rental_categories_deleted_by_fk
        FOREIGN KEY (deleted_by)
        REFERENCES admin_users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS rental_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(190) NOT NULL,
    slug VARCHAR(190) NOT NULL,
    sku VARCHAR(80) NULL,
    description TEXT NOT NULL,
    ideal_use VARCHAR(500) NOT NULL,
    media_reference VARCHAR(190) NOT NULL,

    availability_status VARCHAR(80)
        NOT NULL
        DEFAULT 'Inquire for availability',

    rental_unit VARCHAR(30) NULL,
    rate_amount DECIMAL(12,2) NULL,
    currency CHAR(3) NOT NULL DEFAULT 'PHP',
    security_deposit DECIMAL(12,2) NULL,
    tracks_inventory BOOLEAN NOT NULL DEFAULT FALSE,
    available_quantity INT UNSIGNED NULL,

    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    is_sample BOOLEAN NOT NULL DEFAULT FALSE,
    display_order INT NOT NULL DEFAULT 0,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    deleted_at TIMESTAMP NULL DEFAULT NULL,
    deleted_by BIGINT UNSIGNED NULL,

    UNIQUE INDEX rental_items_slug_unique (slug),
    UNIQUE INDEX rental_items_sku_unique (sku),

    INDEX rental_visibility (
        is_active,
        display_order
    ),

    INDEX rental_items_category_listing (
        category_id,
        is_active,
        deleted_at,
        display_order
    ),

    CONSTRAINT rental_category_fk
        FOREIGN KEY (category_id)
        REFERENCES rental_categories(id)
        ON DELETE RESTRICT,

    CONSTRAINT rental_items_deleted_by_fk
        FOREIGN KEY (deleted_by)
        REFERENCES admin_users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS rental_inclusions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rental_item_id BIGINT UNSIGNED NOT NULL,
    inclusion_text VARCHAR(500) NOT NULL,
    display_order INT NOT NULL DEFAULT 0,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    deleted_at TIMESTAMP NULL DEFAULT NULL,
    deleted_by BIGINT UNSIGNED NULL,

    INDEX rental_inclusion_order (
        rental_item_id,
        display_order
    ),

    INDEX rental_inclusions_listing (
        rental_item_id,
        is_active,
        deleted_at,
        display_order
    ),

    CONSTRAINT rental_inclusion_fk
        FOREIGN KEY (rental_item_id)
        REFERENCES rental_items(id)
        ON DELETE RESTRICT,

    CONSTRAINT rental_inclusions_deleted_by_fk
        FOREIGN KEY (deleted_by)
        REFERENCES admin_users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS rental_item_images (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rental_item_id BIGINT UNSIGNED NOT NULL,
    storage_disk VARCHAR(40) NOT NULL DEFAULT 'local',
    file_path VARCHAR(500) NOT NULL,
    alt_text VARCHAR(500) NOT NULL,
    is_primary BOOLEAN NOT NULL DEFAULT FALSE,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    display_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    deleted_by BIGINT UNSIGNED NULL,

    INDEX rental_item_images_listing (
        rental_item_id,
        is_active,
        deleted_at,
        display_order
    ),

    CONSTRAINT rental_item_images_item_fk
        FOREIGN KEY (rental_item_id)
        REFERENCES rental_items(id)
        ON DELETE RESTRICT,

    CONSTRAINT rental_item_images_deleted_by_fk
        FOREIGN KEY (deleted_by)
        REFERENCES admin_users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS rental_services (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(190) NOT NULL,
    slug VARCHAR(190) NOT NULL,
    description TEXT NOT NULL,
    image_reference VARCHAR(500) NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    display_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    deleted_by BIGINT UNSIGNED NULL,

    UNIQUE INDEX rental_services_slug_unique (slug),

    INDEX rental_services_listing (
        is_active,
        deleted_at,
        display_order
    ),

    CONSTRAINT rental_services_deleted_by_fk
        FOREIGN KEY (deleted_by)
        REFERENCES admin_users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS carts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT UNSIGNED NULL,
    session_token_hash CHAR(64) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    currency CHAR(3) NOT NULL DEFAULT 'PHP',
    expires_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE INDEX carts_session_token_unique (
        session_token_hash
    ),

    INDEX carts_customer_status (
        customer_id,
        status,
        updated_at
    ),

    INDEX carts_expiry (
        status,
        expires_at
    ),

    CONSTRAINT carts_customer_fk
        FOREIGN KEY (customer_id)
        REFERENCES customers(id)
        ON DELETE RESTRICT
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS cart_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cart_id BIGINT UNSIGNED NOT NULL,
    rental_item_id BIGINT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    rental_start_date DATE NULL,
    rental_end_date DATE NULL,
    unit_rate DECIMAL(12,2) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    INDEX cart_items_line (
        cart_id,
        rental_item_id,
        rental_start_date,
        rental_end_date
    ),

    INDEX cart_items_item (
        rental_item_id
    ),

    CONSTRAINT cart_items_cart_fk
        FOREIGN KEY (cart_id)
        REFERENCES carts(id)
        ON DELETE RESTRICT,

    CONSTRAINT cart_items_rental_item_fk
        FOREIGN KEY (rental_item_id)
        REFERENCES rental_items(id)
        ON DELETE RESTRICT
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(40) NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'pending',
    payment_status VARCHAR(40) NOT NULL DEFAULT 'unpaid',
    currency CHAR(3) NOT NULL DEFAULT 'PHP',

    subtotal_amount DECIMAL(12,2)
        NOT NULL DEFAULT 0.00,

    security_deposit_amount DECIMAL(12,2)
        NOT NULL DEFAULT 0.00,

    total_amount DECIMAL(12,2)
        NOT NULL DEFAULT 0.00,

    customer_name_snapshot VARCHAR(200) NOT NULL,
    customer_email_snapshot VARCHAR(190) NOT NULL,
    customer_phone_snapshot VARCHAR(40) NULL,

    billing_address_snapshot TEXT NULL,
    delivery_address_snapshot TEXT NULL,

    customer_notes TEXT NULL,
    admin_notes TEXT NULL,

    placed_at TIMESTAMP NULL DEFAULT NULL,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    cancelled_at TIMESTAMP NULL DEFAULT NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE INDEX orders_number_unique (
        order_number
    ),

    INDEX orders_customer_created (
        customer_id,
        created_at
    ),

    INDEX orders_status_created (
        status,
        created_at
    ),

    INDEX orders_payment_status_created (
        payment_status,
        created_at
    ),

    CONSTRAINT orders_customer_fk
        FOREIGN KEY (customer_id)
        REFERENCES customers(id)
        ON DELETE RESTRICT
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS order_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    rental_item_id BIGINT UNSIGNED NOT NULL,

    item_name_snapshot VARCHAR(190) NOT NULL,
    sku_snapshot VARCHAR(80) NULL,
    description_snapshot TEXT NULL,
    inclusions_snapshot TEXT NULL,
    rental_unit_snapshot VARCHAR(30) NULL,

    unit_rate DECIMAL(12,2)
        NOT NULL DEFAULT 0.00,

    quantity INT UNSIGNED
        NOT NULL DEFAULT 1,

    rental_start_date DATE NULL,
    rental_end_date DATE NULL,

    line_total DECIMAL(12,2)
        NOT NULL DEFAULT 0.00,

    created_at TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX order_items_order (
        order_id,
        id
    ),

    INDEX order_items_catalogue_reference (
        rental_item_id
    ),

    CONSTRAINT order_items_order_fk
        FOREIGN KEY (order_id)
        REFERENCES orders(id)
        ON DELETE RESTRICT,

    CONSTRAINT order_items_rental_item_fk
        FOREIGN KEY (rental_item_id)
        REFERENCES rental_items(id)
        ON DELETE RESTRICT
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS order_status_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(40) NOT NULL,
    note TEXT NULL,

    changed_by_type VARCHAR(20)
        NOT NULL DEFAULT 'system',

    changed_by_id BIGINT UNSIGNED NULL,

    created_at TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX order_status_history_order (
        order_id,
        created_at
    ),

    INDEX order_status_history_status (
        status,
        created_at
    ),

    CONSTRAINT order_status_history_order_fk
        FOREIGN KEY (order_id)
        REFERENCES orders(id)
        ON DELETE RESTRICT
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS payment_methods (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(80) NOT NULL,
    name VARCHAR(120) NOT NULL,
    method_type VARCHAR(30) NOT NULL,
    provider VARCHAR(80) NULL,

    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    display_order INT NOT NULL DEFAULT 0,

    created_at TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    deleted_at TIMESTAMP NULL DEFAULT NULL,
    deleted_by BIGINT UNSIGNED NULL,

    UNIQUE INDEX payment_methods_code_unique (
        code
    ),

    INDEX payment_methods_listing (
        is_active,
        deleted_at,
        display_order
    ),

    CONSTRAINT payment_methods_deleted_by_fk
        FOREIGN KEY (deleted_by)
        REFERENCES admin_users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    payment_method_id BIGINT UNSIGNED NOT NULL,

    payment_reference VARCHAR(80) NOT NULL,

    provider_transaction_id VARCHAR(190) NULL,

    amount DECIMAL(12,2) NOT NULL,

    currency CHAR(3)
        NOT NULL DEFAULT 'PHP',

    status VARCHAR(40)
        NOT NULL DEFAULT 'pending',

    paid_at TIMESTAMP NULL DEFAULT NULL,
    failed_at TIMESTAMP NULL DEFAULT NULL,

    created_at TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE INDEX payments_reference_unique (
        payment_reference
    ),

    UNIQUE INDEX payments_provider_transaction_unique (
        provider_transaction_id
    ),

    INDEX payments_order (
        order_id,
        created_at
    ),

    INDEX payments_status_created (
        status,
        created_at
    ),

    INDEX payments_method_created (
        payment_method_id,
        created_at
    ),

    CONSTRAINT payments_order_fk
        FOREIGN KEY (order_id)
        REFERENCES orders(id)
        ON DELETE RESTRICT,

    CONSTRAINT payments_method_fk
        FOREIGN KEY (payment_method_id)
        REFERENCES payment_methods(id)
        ON DELETE RESTRICT
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS payment_proofs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_id BIGINT UNSIGNED NOT NULL,

    storage_disk VARCHAR(40) NOT NULL,

    file_path VARCHAR(500) NOT NULL,

    original_name VARCHAR(255) NOT NULL,

    mime_type VARCHAR(100) NOT NULL,

    size_bytes BIGINT UNSIGNED NOT NULL,

    checksum_sha256 CHAR(64) NOT NULL,

    status VARCHAR(40)
        NOT NULL DEFAULT 'pending_review',

    uploaded_at TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    reviewed_at TIMESTAMP NULL DEFAULT NULL,

    reviewed_by_admin_id BIGINT UNSIGNED NULL,

    review_note TEXT NULL,

    created_at TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    INDEX payment_proofs_payment (
        payment_id,
        created_at
    ),

    INDEX payment_proofs_review_queue (
        status,
        uploaded_at
    ),

    INDEX payment_proofs_reviewer (
        reviewed_by_admin_id,
        reviewed_at
    ),

    CONSTRAINT payment_proofs_payment_fk
        FOREIGN KEY (payment_id)
        REFERENCES payments(id)
        ON DELETE RESTRICT,

    CONSTRAINT payment_proofs_reviewer_fk
        FOREIGN KEY (reviewed_by_admin_id)
        REFERENCES admin_users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS payment_proof_reviews (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_proof_id BIGINT UNSIGNED NOT NULL,

    decision VARCHAR(30) NOT NULL,

    note TEXT NULL,

    reviewed_by_admin_id BIGINT UNSIGNED NOT NULL,

    created_at TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX payment_proof_reviews_proof (
        payment_proof_id,
        created_at
    ),

    INDEX payment_proof_reviews_admin (
        reviewed_by_admin_id,
        created_at
    ),

    CONSTRAINT payment_proof_reviews_proof_fk
        FOREIGN KEY (payment_proof_id)
        REFERENCES payment_proofs(id)
        ON DELETE RESTRICT,

    CONSTRAINT payment_proof_reviews_admin_fk
        FOREIGN KEY (reviewed_by_admin_id)
        REFERENCES admin_users(id)
        ON DELETE RESTRICT
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS support_content (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    slug VARCHAR(190) NOT NULL,

    title VARCHAR(190) NOT NULL,

    content_body TEXT NOT NULL,

    content_type VARCHAR(40)
        NOT NULL DEFAULT 'support',

    is_active BOOLEAN
        NOT NULL DEFAULT TRUE,

    display_order INT
        NOT NULL DEFAULT 0,

    created_at TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    deleted_at TIMESTAMP NULL DEFAULT NULL,

    deleted_by BIGINT UNSIGNED NULL,

    UNIQUE INDEX support_content_slug_unique (
        slug
    ),

    INDEX support_content_listing (
        content_type,
        is_active,
        deleted_at,
        display_order
    ),

    CONSTRAINT support_content_deleted_by_fk
        FOREIGN KEY (deleted_by)
        REFERENCES admin_users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;