-- Migration: Add RCMP record expiration date to user_services
-- Date: 2026-04-08
-- For Waiver service type — tracks when the RCMP criminal record expires.

USE mysql_clients_portal;

ALTER TABLE user_services
    ADD COLUMN rcmp_record_exp_date DATE NULL AFTER status_explanation;
