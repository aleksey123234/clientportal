-- Migration: Add read_at timestamp to service_email_history
-- Date: 2026-04-08
-- Tracks when a client first viewed an email in the inbox.

USE mysql_clients_portal;

ALTER TABLE service_email_history
    ADD COLUMN read_at DATETIME NULL AFTER subject;
