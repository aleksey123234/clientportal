-- Invite-link registration: CRM sets registration_token (UUID) on a pending user.
-- Client opens /register/{uuid}, sets password; portal clears the token and activates.
-- Idempotent: safe if column/index already exist. No AFTER remember_token (see 022).

SET @db := DATABASE();

SET @exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'users' AND COLUMN_NAME = 'registration_token'
);
SET @sql := IF(@exists = 0,
    'ALTER TABLE users ADD COLUMN registration_token CHAR(36) NULL DEFAULT NULL COMMENT ''Invite UUID for /register/{token}; NULL after activation''',
    'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'users' AND INDEX_NAME = 'uq_users_registration_token'
);
SET @sql := IF(@exists = 0,
    'ALTER TABLE users ADD UNIQUE KEY uq_users_registration_token (registration_token)',
    'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
