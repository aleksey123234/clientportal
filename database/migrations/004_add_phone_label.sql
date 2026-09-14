-- Add label column for "Other" phone type (idempotent).
-- Allows user to describe whose phone it is, e.g. "Janet's Phone"
-- 003c already includes label; this is a no-op on fresh installs.

SET @db := DATABASE();
SET @exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'client_phones' AND COLUMN_NAME = 'label'
);
SET @sql := IF(@exists = 0,
    'ALTER TABLE client_phones ADD COLUMN label VARCHAR(100) NULL AFTER extension',
    'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
