-- Migration: NEXUS backup codes table
-- Date: 2026-04-07
-- Adds the service_nexus_codes table for NEXUS backup/recovery codes.

USE mysql_clients_portal;

CREATE TABLE IF NOT EXISTS service_nexus_codes (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_service_id INT UNSIGNED    NOT NULL,
    backup_code     VARCHAR(16)     NOT NULL,              -- max 16 chars
    is_used         TINYINT(1)      NOT NULL DEFAULT 0,
    date_used       DATE            NULL,
    computer_name   VARCHAR(150)    NULL,
    used_by         VARCHAR(150)    NULL,                  -- staff/user name
    browser         VARCHAR(100)    NULL,
    sort_order      INT             NOT NULL DEFAULT 0,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_service_id) REFERENCES user_services(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
