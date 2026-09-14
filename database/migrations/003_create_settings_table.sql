USE mysql_clients_portal;

-- ============================================================
-- Client documents table
-- ============================================================
CREATE TABLE IF NOT EXISTS documents (
    id            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    user_id       INT UNSIGNED    NOT NULL,
    title         VARCHAR(255)    NOT NULL,
    file_name     VARCHAR(255)    NOT NULL,
    file_path     VARCHAR(500)    NOT NULL,
    file_size     INT UNSIGNED    DEFAULT NULL,            -- in bytes
    mime_type     VARCHAR(100)    DEFAULT NULL,
    uploaded_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_docs_user_id (user_id),
    CONSTRAINT fk_docs_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Client payments table
-- ============================================================
CREATE TABLE IF NOT EXISTS payments (
    id              INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED        NOT NULL,
    amount          DECIMAL(10,2)       NOT NULL,
    currency        CHAR(3)             NOT NULL DEFAULT 'USD',
    status          ENUM('pending','completed','failed','refunded') NOT NULL DEFAULT 'pending',
    description     VARCHAR(255)        DEFAULT NULL,
    transaction_id  VARCHAR(100)        DEFAULT NULL,      -- ID from payment gateway
    paid_at         DATETIME            DEFAULT NULL,
    created_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_payments_user_id (user_id),
    CONSTRAINT fk_payments_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- User settings table (portal preferences)
-- ============================================================
CREATE TABLE IF NOT EXISTS user_settings (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED    NOT NULL,
    setting_key     VARCHAR(100)    NOT NULL,
    setting_value   TEXT            DEFAULT NULL,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_settings_user_key (user_id, setting_key),
    CONSTRAINT fk_settings_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Sessions table (for token-based auth without PHP sessions)
-- ============================================================
CREATE TABLE IF NOT EXISTS sessions (
    id          VARCHAR(128)    NOT NULL,
    user_id     INT UNSIGNED    NOT NULL,
    ip_address  VARCHAR(45)     DEFAULT NULL,
    user_agent  VARCHAR(300)    DEFAULT NULL,
    expires_at  DATETIME        NOT NULL,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_sessions_user_id (user_id),
    CONSTRAINT fk_sessions_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
