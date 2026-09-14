-- Migration 018: Add received flag to courts/police list items
-- Run: mysql -u root -p mysql_clients_portal < database/migrations/018_add_courts_police_received.sql

USE mysql_clients_portal;

ALTER TABLE service_courts_police
    ADD COLUMN received TINYINT(1) NOT NULL DEFAULT 0 AFTER city;

-- Demo: mark Toronto Court of Appeal as received
UPDATE service_courts_police
SET received = 1
WHERE name = 'Toronto Court of Appeal' AND city = 'Toronto'
LIMIT 1;
