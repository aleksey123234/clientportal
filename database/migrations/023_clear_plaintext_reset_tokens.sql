-- Clear plaintext password-reset tokens before SHA-256 storage (Wave 0).
-- Idempotent: no-op if password_reset_token column is missing (added in 022).
-- Users must request a new reset link after deploy when column held plaintext.

SET @db := DATABASE();
SET @exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'users' AND COLUMN_NAME = 'password_reset_token'
);
SET @sql := IF(@exists > 0,
    'UPDATE users SET password_reset_token = NULL, password_reset_expires = NULL WHERE password_reset_token IS NOT NULL',
    'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
