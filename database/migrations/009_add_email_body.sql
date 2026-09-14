-- Migration 009: Add body column to service_email_history
-- Run: mysql -u root -p mysql_clients_portal < database/migrations/009_add_email_body.sql

ALTER TABLE service_email_history
    ADD COLUMN body TEXT NULL AFTER subject;
