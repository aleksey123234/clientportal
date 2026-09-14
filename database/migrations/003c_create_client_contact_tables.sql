-- Create client contact tables (phones / emails / addresses).
-- Must run before 004 / 008 ALTERs. Matches ProfileController column names.
-- CREATE IF NOT EXISTS so existing portals are safe.

CREATE TABLE IF NOT EXISTS client_phones (
    id            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    user_id       INT UNSIGNED    NOT NULL,
    type          VARCHAR(30)     NOT NULL DEFAULT 'other',
    number        VARCHAR(30)     NOT NULL,
    extension     VARCHAR(10)     DEFAULT NULL,
    label         VARCHAR(100)    DEFAULT NULL,
    is_main       TINYINT(1)      NOT NULL DEFAULT 0,
    is_old        TINYINT(1)      NOT NULL DEFAULT 0,
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_phones_user_id (user_id),
    CONSTRAINT fk_phones_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS client_emails (
    id            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    user_id       INT UNSIGNED    NOT NULL,
    type          VARCHAR(30)     NOT NULL DEFAULT 'alternative',
    email         VARCHAR(180)    NOT NULL,
    is_main       TINYINT(1)      NOT NULL DEFAULT 0,
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_emails_user_id (user_id),
    KEY idx_emails_email (email),
    CONSTRAINT fk_emails_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS client_addresses (
    id               INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    user_id          INT UNSIGNED    NOT NULL,
    address_type     VARCHAR(30)     NOT NULL COMMENT 'living | mail',
    country          VARCHAR(100)    DEFAULT NULL,
    province_state   VARCHAR(100)    DEFAULT NULL,
    city             VARCHAR(100)    DEFAULT NULL,
    postal_code      VARCHAR(20)     DEFAULT NULL,
    street           VARCHAR(255)    DEFAULT NULL,
    unit             VARCHAR(50)     DEFAULT NULL,
    move_in_date     DATE            DEFAULT NULL,
    created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_addr_user_type (user_id, address_type),
    CONSTRAINT fk_addr_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
