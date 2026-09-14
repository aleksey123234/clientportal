-- Migration: CIF (Client Information File) system
-- Date: 2026-04-09
-- Adds tables to store CIF form responses and track generated PDFs.

USE mysql_clients_portal;

-- ─────────────────────────────────────────────────────────────
-- 1. CIF Responses — stores all answers as a JSON blob per user
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS cif_responses (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    response_data   JSON         NOT NULL COMMENT 'All CIF answers keyed by question key',
    progress    TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Percentage complete 0-100',
    completed_at DATETIME     NULL COMMENT 'When the user finished filling all required fields',
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY idx_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- 2. CIF Generated PDFs — tracks each generated form file
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS cif_generated_pdfs (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED  NOT NULL,
    form_type       VARCHAR(30)   NOT NULL COMMENT 'Service type: pardon, trp, waiver, etc.',
    file_path       VARCHAR(500)  NOT NULL COMMENT 'Relative path from UPLOAD_BASE_PATH',
    page_count      TINYINT UNSIGNED NOT NULL DEFAULT 1,
    generated_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_form (user_id, form_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
