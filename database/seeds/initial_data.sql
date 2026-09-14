-- DEV ONLY — do not run on production.
-- OBSOLETE: references legacy tables (username/clients/settings) that the portal does not use.
-- Prefer payments_seed.sql / services_seed.sql against a migrated schema.
-- SQL script to seed initial data for the client portal database

-- Insert initial users
INSERT INTO users (username, password, email, created_at) VALUES
('admin', 'hashed_password_1', 'admin@example.com', NOW()),
('user1', 'hashed_password_2', 'user1@example.com', NOW()),
('user2', 'hashed_password_3', 'user2@example.com', NOW());

-- Insert initial clients
INSERT INTO clients (name, email, phone, created_at) VALUES
('Client A', 'clientA@example.com', '123-456-7890', NOW()),
('Client B', 'clientB@example.com', '098-765-4321', NOW());

-- Insert initial settings
INSERT INTO settings (key, value, created_at) VALUES
('site_name', 'Client Portal', NOW()),
('maintenance_mode', '0', NOW()),
('default_language', 'en', NOW());