-- 007: Add middle_name column to users table
-- Supports the split from single "Full Name" into First / Middle / Last fields.

ALTER TABLE users
    ADD COLUMN middle_name VARCHAR(100) NOT NULL DEFAULT '' AFTER first_name;
