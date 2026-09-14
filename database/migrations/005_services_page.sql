-- Migration: Services page tables
-- Date: 2026-04-07
-- Adds tables for the Services page: per-user services with statuses,
-- courts/police, email history, SBC/PCC records, and conviction summaries.

USE mysql_clients_portal;

-- ─────────────────────────────────────────────────────────────
-- 1. User Services — links a user to a service with metadata
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS user_services (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED    NOT NULL,
    service_cost_id INT UNSIGNED    NOT NULL,
    date_added      DATE            NOT NULL,
    current_status  VARCHAR(255)    NOT NULL DEFAULT 'Pending',
    status_explanation TEXT         NULL,      -- tooltip text (? icon)
    notes           TEXT            NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)         REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (service_cost_id) REFERENCES service_costs(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- 2. Service Courts & Police — associated institutions
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS service_courts_police (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_service_id INT UNSIGNED    NOT NULL,
    type            ENUM('court','police') NOT NULL,
    name            VARCHAR(255)    NOT NULL,
    city            VARCHAR(100)    NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_service_id) REFERENCES user_services(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- 3. Service Email History — emails sent to the client
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS service_email_history (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_service_id INT UNSIGNED    NOT NULL,
    email_number    INT             NOT NULL DEFAULT 0,    -- template number (0 = custom)
    sent_at         DATETIME        NOT NULL,
    sent_by         VARCHAR(150)    NOT NULL,              -- staff name
    subject         VARCHAR(255)    NOT NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_service_id) REFERENCES user_services(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- 4. SBC/PCC Records — for TRP and Criminal Rehab services
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS service_sbc_records (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_service_id INT UNSIGNED    NOT NULL,
    bc_name         VARCHAR(150)    NOT NULL,              -- e.g. "FBI", country name
    ordered_cf      TINYINT(1)      NOT NULL DEFAULT 0,    -- checkbox
    ordered_cf_date DATE            NULL,
    ordered_mo      TINYINT(1)      NOT NULL DEFAULT 0,    -- checkbox
    ordered_mo_date DATE            NULL,
    order_date      DATE            NULL,
    exp_date        DATE            NULL,
    snd_exp_date    DATE            NULL,                  -- 2nd expiration
    sort_order      INT             NOT NULL DEFAULT 0,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_service_id) REFERENCES user_services(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- 5. Conviction Summary — for TRP and Criminal Rehab services
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS service_conviction_records (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_service_id INT UNSIGNED    NOT NULL,
    cycle_num       INT             NOT NULL DEFAULT 1,    -- arrest cycle #
    received        TINYINT(1)      NOT NULL DEFAULT 0,    -- Rc'd checkbox
    imm             TINYINT(1)      NOT NULL DEFAULT 0,    -- IMM checkbox
    arrest_date     DATE            NULL,
    place           VARCHAR(200)    NULL,
    charge          TEXT            NULL,
    disposition     TEXT            NULL,
    notes           TEXT            NULL,
    sort_order      INT             NOT NULL DEFAULT 0,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_service_id) REFERENCES user_services(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- 6. NEXUS Application Tracking — for NEXUS service
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS service_nexus_records (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_service_id INT UNSIGNED    NOT NULL,
    application_status VARCHAR(100) NOT NULL DEFAULT 'Pending',
    ttp_account     VARCHAR(100)    NULL,                  -- TTP account number
    interview_date  DATE            NULL,
    interview_location VARCHAR(200) NULL,
    conditional_approval_date DATE  NULL,
    card_issued_date DATE           NULL,
    card_expiry_date DATE           NULL,
    nexus_card_number VARCHAR(50)   NULL,
    notes           TEXT            NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_service_id) REFERENCES user_services(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
