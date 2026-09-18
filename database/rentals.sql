CREATE TABLE IF NOT EXISTS rental_categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    name VARCHAR(120) NOT NULL,

    slug VARCHAR(120) NOT NULL UNIQUE,

    is_active BOOLEAN NOT NULL DEFAULT TRUE,

    display_order INT NOT NULL DEFAULT 0
)
ENGINE = InnoDB
DEFAULT CHARSET = utf8mb4
COLLATE = utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS rental_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    category_id BIGINT UNSIGNED NOT NULL,

    name VARCHAR(190) NOT NULL,

    slug VARCHAR(190) NOT NULL UNIQUE,

    description TEXT NOT NULL,

    ideal_use VARCHAR(500) NOT NULL,

    media_reference VARCHAR(190) NOT NULL,

    availability_status VARCHAR(80)
        NOT NULL
        DEFAULT 'Inquire for availability',

    is_active BOOLEAN NOT NULL DEFAULT TRUE,

    is_sample BOOLEAN NOT NULL DEFAULT FALSE,

    display_order INT NOT NULL DEFAULT 0,

    created_at TIMESTAMP
        NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP
        NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    INDEX rental_visibility (
        is_active,
        display_order
    ),

    CONSTRAINT rental_category_fk
        FOREIGN KEY (category_id)
        REFERENCES rental_categories(id)
)
ENGINE = InnoDB
DEFAULT CHARSET = utf8mb4
COLLATE = utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS rental_inclusions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    rental_item_id BIGINT UNSIGNED NOT NULL,

    inclusion_text VARCHAR(500) NOT NULL,

    display_order INT NOT NULL DEFAULT 0,

    INDEX rental_inclusion_order (
        rental_item_id,
        display_order
    ),

    CONSTRAINT rental_inclusion_fk
        FOREIGN KEY (rental_item_id)
        REFERENCES rental_items(id)
        ON DELETE CASCADE
)
ENGINE = InnoDB
DEFAULT CHARSET = utf8mb4
COLLATE = utf8mb4_unicode_ci;