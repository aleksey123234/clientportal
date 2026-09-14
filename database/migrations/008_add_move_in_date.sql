-- Migration 008: Add move_in_date to client_addresses (idempotent).
-- 003c already includes move_in_date; this is a no-op on fresh installs.

SET @db := DATABASE();
SET @exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'client_addresses' AND COLUMN_NAME = 'move_in_date'
);
SET @sql := IF(@exists = 0,
    'ALTER TABLE client_addresses ADD COLUMN move_in_date DATE NULL AFTER unit',
    'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
