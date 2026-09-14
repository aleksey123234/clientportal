USE mysql_clients_portal;

-- ============================================================
-- Portal users table (clients who log in)
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    email         VARCHAR(180)    NOT NULL,
    password_hash VARCHAR(255)    NOT NULL,               -- bcrypt hash
    first_name    VARCHAR(100)    NOT NULL DEFAULT '',
    last_name     VARCHAR(100)    NOT NULL DEFAULT '',
    phone         VARCHAR(30)     DEFAULT NULL,
    status        TINYINT(1)      NOT NULL DEFAULT 1,     -- 1=active, 0=disabled
    role          ENUM('client','admin') NOT NULL DEFAULT 'client',
    last_login_at DATETIME        DEFAULT NULL,
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
