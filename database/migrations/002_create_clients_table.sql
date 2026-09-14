USE mysql_clients_portal;

-- ============================================================
-- Client profiles table (CIF — Client Information File)
-- Linked to users 1:1
-- ============================================================
CREATE TABLE IF NOT EXISTS client_profiles (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED    NOT NULL,
    case_number     VARCHAR(50)     DEFAULT NULL,          -- case number in CRM
    attorney_name   VARCHAR(150)    DEFAULT NULL,
    court_name      VARCHAR(200)    DEFAULT NULL,
    case_status     VARCHAR(100)    DEFAULT NULL,
    address         VARCHAR(255)    DEFAULT NULL,
    city            VARCHAR(100)    DEFAULT NULL,
    state           VARCHAR(50)     DEFAULT NULL,
    zip_code        VARCHAR(20)     DEFAULT NULL,
    ssn_last4       CHAR(4)         DEFAULT NULL,          -- last 4 digits of SSN
    dob             DATE            DEFAULT NULL,
    notes           TEXT            DEFAULT NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_cp_user_id (user_id),
    CONSTRAINT fk_cp_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
