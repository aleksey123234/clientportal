-- Migration 025: add profile preference fields used by ProfileService.
-- Idempotent so it is safe for both fresh and existing databases.

SET @db := DATABASE();

SET @exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'client_profiles'
      AND COLUMN_NAME = 'preferred_name'
);
SET @sql := IF(
    @exists = 0,
    'ALTER TABLE client_profiles ADD COLUMN preferred_name VARCHAR(100) NULL AFTER dob',
    'DO 0'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'client_profiles'
      AND COLUMN_NAME = 'additional_contact'
);
SET @sql := IF(
    @exists = 0,
    'ALTER TABLE client_profiles ADD COLUMN additional_contact VARCHAR(255) NULL AFTER preferred_name',
    'DO 0'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'client_profiles'
      AND COLUMN_NAME = 'timezone'
);
SET @sql := IF(
    @exists = 0,
    'ALTER TABLE client_profiles ADD COLUMN timezone VARCHAR(10) NULL AFTER additional_contact',
    'DO 0'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'client_profiles'
      AND COLUMN_NAME = 'has_printer'
);
SET @sql := IF(
    @exists = 0,
    'ALTER TABLE client_profiles ADD COLUMN has_printer TINYINT(1) NULL AFTER timezone',
    'DO 0'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
