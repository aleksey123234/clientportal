-- Migration: Add credit card display columns to users table
-- Date: 2026-04-09
-- Stores masked CC info for display on the Payments page.
-- Only last 4 digits and expiry are stored (no full card numbers).

USE mysql_clients_portal;

ALTER TABLE users
    ADD COLUMN cc_last4      CHAR(4)      NULL COMMENT 'Last 4 digits of active credit card' AFTER phone,
    ADD COLUMN cc_expiry     VARCHAR(7)   NULL COMMENT 'Card expiry MM/YYYY' AFTER cc_last4;

-- Seed test data for user 1
UPDATE users SET cc_last4 = '4829', cc_expiry = '09/2027' WHERE id = 1;
