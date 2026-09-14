-- Auth token columns on users (idempotent).
-- remember_token: SHA-256 hex of cookie (Phase 1).
-- password_reset_*: forgot/reset flow (PasswordResetController).

SET @db := DATABASE();

-- remember_token
SET @exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'users' AND COLUMN_NAME = 'remember_token'
);
SET @sql := IF(@exists = 0,
    'ALTER TABLE users ADD COLUMN remember_token VARCHAR(255) NULL DEFAULT NULL COMMENT ''SHA-256 hex of remember-me cookie''',
    'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- password_reset_token
SET @exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'users' AND COLUMN_NAME = 'password_reset_token'
);
SET @sql := IF(@exists = 0,
    'ALTER TABLE users ADD COLUMN password_reset_token VARCHAR(64) NULL DEFAULT NULL',
    'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- password_reset_expires
SET @exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'users' AND COLUMN_NAME = 'password_reset_expires'
);
SET @sql := IF(@exists = 0,
    'ALTER TABLE users ADD COLUMN password_reset_expires DATETIME NULL DEFAULT NULL',
    'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Index on reset token (optional lookup aid)
SET @exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'users' AND INDEX_NAME = 'idx_users_password_reset_token'
);
SET @sql := IF(@exists = 0,
    'ALTER TABLE users ADD KEY idx_users_password_reset_token (password_reset_token)',
    'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
