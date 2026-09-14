-- Migration: Add payments system tables & client_id column
-- Date: 2026-04-07
-- Updated: 2026-04-08 — added completed_fpws status, cheque method support

-- 1. Add client_id to users (idempotent-ish: fresh always needs it)
SET @db := DATABASE();
SET @exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'users' AND COLUMN_NAME = 'client_id'
);
SET @sql := IF(@exists = 0,
    'ALTER TABLE users ADD COLUMN client_id VARCHAR(20) NULL AFTER id',
    'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE users SET client_id = '1817' WHERE id = 1 AND (client_id IS NULL OR client_id = '');

SET @exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'users' AND INDEX_NAME = 'idx_client_id'
);
SET @sql := IF(@exists = 0,
    'ALTER TABLE users ADD UNIQUE INDEX idx_client_id (client_id)',
    'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 1b. documents catalog columns (003 created a minimal documents table)
SET @exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'documents' AND COLUMN_NAME = 'status'
);
SET @sql := IF(@exists = 0,
    'ALTER TABLE documents ADD COLUMN status ENUM(''pending'',''uploaded'',''approved'',''completed_fpws'') NOT NULL DEFAULT ''pending'' AFTER mime_type',
    'ALTER TABLE documents MODIFY COLUMN status ENUM(''pending'',''uploaded'',''approved'',''completed_fpws'') NOT NULL DEFAULT ''pending''');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'documents' AND COLUMN_NAME = 'service_type'
);
SET @sql := IF(@exists = 0,
    'ALTER TABLE documents ADD COLUMN service_type VARCHAR(30) NOT NULL DEFAULT '''' AFTER user_id',
    'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'documents' AND COLUMN_NAME = 'doc_key'
);
SET @sql := IF(@exists = 0,
    'ALTER TABLE documents ADD COLUMN doc_key VARCHAR(50) NOT NULL DEFAULT '''' AFTER service_type',
    'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Replace legacy payments table from 003_create_settings_table
DROP TABLE IF EXISTS payments;

-- 2. Service costs (both main and extra services)
CREATE TABLE IF NOT EXISTS service_costs (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    service_type   VARCHAR(30)   NOT NULL,
    label          VARCHAR(100)  NOT NULL,
    total_cost     DECIMAL(10,2) NOT NULL DEFAULT 0,
    is_extra       TINYINT(1)    NOT NULL DEFAULT 0,
    created_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 3. Payment plans linking a user to a service with installment schedule
--    TRP plans use 16 installments (12 standard + 4 extra months).
CREATE TABLE IF NOT EXISTS payment_plans (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED  NOT NULL,
    service_cost_id INT UNSIGNED  NOT NULL,
    total_amount    DECIMAL(10,2) NOT NULL,
    installments    INT           NOT NULL DEFAULT 1,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)         REFERENCES users(id),
    FOREIGN KEY (service_cost_id) REFERENCES service_costs(id)
);

-- 4. Individual payment installments
--    method is VARCHAR(50) to support: credit_card, debit, e_transfer, cash, cheque
CREATE TABLE IF NOT EXISTS payments (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id            INT UNSIGNED  NOT NULL,
    payment_plan_id    INT UNSIGNED  NOT NULL,
    installment_number INT           NOT NULL DEFAULT 1,
    amount             DECIMAL(10,2) NOT NULL,
    due_date           DATE          NOT NULL,
    paid_date          DATE          NULL,
    status             ENUM('paid','pending','missed') NOT NULL DEFAULT 'pending',
    method             VARCHAR(50)   NULL,
    reference_number   VARCHAR(100)  NULL,
    notes              TEXT          NULL,
    created_at         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)        REFERENCES users(id),
    FOREIGN KEY (payment_plan_id) REFERENCES payment_plans(id)
);

-- 5. NSF fees linked to missed payments
CREATE TABLE IF NOT EXISTS nsf_fees (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_id INT UNSIGNED  NOT NULL,
    amount     DECIMAL(10,2) NOT NULL DEFAULT 25.00,
    created_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (payment_id) REFERENCES payments(id)
);

-- 6. Extra services assigned to a user
CREATE TABLE IF NOT EXISTS user_extra_services (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED  NOT NULL,
    service_cost_id INT UNSIGNED  NOT NULL,
    amount          DECIMAL(10,2) NOT NULL,
    status          ENUM('active','completed','cancelled') NOT NULL DEFAULT 'active',
    notes           TEXT          NULL,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)         REFERENCES users(id),
    FOREIGN KEY (service_cost_id) REFERENCES service_costs(id)
);
