-- Migration: Clean up service_sbc_records table
-- Date: 2026-04-09
-- Drops unused CRM-internal columns (ordered_cf, ordered_mo, order_date)
-- that are not displayed in the client portal.
-- Renames bc_name → issuing_authority for clarity.

USE mysql_clients_portal;

-- Drop unused columns
ALTER TABLE service_sbc_records
    DROP COLUMN ordered_cf,
    DROP COLUMN ordered_cf_date,
    DROP COLUMN ordered_mo,
    DROP COLUMN ordered_mo_date,
    DROP COLUMN order_date;

-- Rename bc_name to issuing_authority for readability
ALTER TABLE service_sbc_records
    CHANGE COLUMN bc_name issuing_authority VARCHAR(150) NOT NULL
    COMMENT 'Issuing authority name (e.g. FBI, country name)';
