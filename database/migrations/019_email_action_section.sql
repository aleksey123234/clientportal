-- Migration 019: Portal CTA section for each inbox email
-- Run: mysql -u root -p mysql_clients_portal < database/migrations/019_email_action_section.sql
-- CRM will set action_section when inserting rows later; Portal only reads it.

USE mysql_clients_portal;

ALTER TABLE service_email_history
    ADD COLUMN action_section ENUM('profile','cif','documents') NULL
        COMMENT 'Portal CTA target; NULL = no link (Custom Update)'
        AFTER body;

-- Backfill demo / existing rows by subject heuristics (one-time)
UPDATE service_email_history
SET action_section = 'profile'
WHERE action_section IS NULL
  AND (
    subject LIKE '%Wrong%address%'
    OR subject LIKE '%Wrong%phone%'
    OR subject LIKE '%mailing address%'
  );

UPDATE service_email_history
SET action_section = 'cif'
WHERE action_section IS NULL
  AND (
    subject LIKE '%CIF%'
    OR subject LIKE '%Client information%'
    OR subject LIKE '%Missing information%'
  );

UPDATE service_email_history
SET action_section = 'documents'
WHERE action_section IS NULL
  AND subject NOT LIKE 'Custom Update%'
  AND (
    subject LIKE '%document%'
    OR subject LIKE '%LPRC%'
    OR subject LIKE '%form%'
    OR subject LIKE '%upload%'
    OR subject LIKE '%CCR%'
    OR subject LIKE '%photo%'
  );

-- Custom Update and anything unmatched stay NULL (no CTA)
