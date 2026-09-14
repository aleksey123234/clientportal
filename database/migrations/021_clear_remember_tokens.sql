-- Clear plaintext remember-me tokens before SHA-256 storage (Phase 1).
-- Idempotent: no-op if remember_token column is missing (added in 022 on fresh installs).
-- Users must re-check "Remember me" after deploy when column already existed with plaintext.

SET @db := DATABASE();
SET @exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'users' AND COLUMN_NAME = 'remember_token'
);
SET @sql := IF(@exists > 0,
    'UPDATE users SET remember_token = NULL WHERE remember_token IS NOT NULL',
    'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
