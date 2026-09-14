-- ============================================================
-- Migration 015 — Drop unused avatar columns from users table
-- ============================================================
-- Idempotent. Uses DATABASE() so runner works on any schema name.

SET @db := DATABASE();

SET @col_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME   = 'users'
      AND COLUMN_NAME  = 'avatar_path'
);
SET @sql = IF(@col_exists > 0,
    'ALTER TABLE users DROP COLUMN avatar_path',
    'DO 0'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists2 = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME   = 'users'
      AND COLUMN_NAME  = 'avatar'
);
SET @sql2 = IF(@col_exists2 > 0,
    'ALTER TABLE users DROP COLUMN avatar',
    'DO 0'
);
PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;
