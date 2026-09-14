-- DEV ONLY — do not run on production.
-- Hardcoded user_id = 1 / demo service rows. Safe only on local/dev DBs.
-- Seed: Services page demo data for user 1
-- Covers: Pardon, Criminal Rehab, TRP, NEXUS, Expunging, Waiver
USE mysql_clients_portal;

-- ═══════════════════════════════════════════════════════════════
-- USER SERVICES (one per main service for user 1)
-- ═══════════════════════════════════════════════════════════════
INSERT INTO user_services (user_id, service_cost_id, date_added, current_status, status_explanation) VALUES
-- id=1  Pardon
(1, 1, '2024-10-14', 'Waiting for LPRC from Police Department',
 'We sent your forms to the Police Department to fill out their part of the forms and mail them back to us. This takes approximately 3 months.'),
-- id=2  Criminal Rehabilitation
(1, 2, '2024-11-05', 'Application Submitted to IRCC',
 'Your Criminal Rehabilitation application has been submitted to Immigration, Refugees and Citizenship Canada. Processing time is 6–12 months.'),
-- id=3  TRP
(1, 3, '2025-01-20', 'Awaiting Biometrics Appointment',
 'We are waiting for your biometrics appointment to be scheduled by IRCC. You will receive an instruction letter.'),
-- id=4  NEXUS
(1, 4, '2025-03-10', 'Conditional Approval — Interview Pending',
 'Your NEXUS application has been conditionally approved. You need to schedule an enrollment interview at a NEXUS Enrollment Center.'),
-- id=5  Expunging
(1, 5, '2024-10-14', 'Record Destruction Order Filed',
 'The application to destroy your record has been filed. The Parole Board will review and issue a destruction order.'),
-- id=6  Waiver
(1, 6, '2024-12-01', 'Waiver Application Mailed to DHS',
 'Your US Entry Waiver application has been mailed to the Department of Homeland Security. Processing takes 6–12 months.');

-- ═══════════════════════════════════════════════════════════════
-- COURTS & POLICE
-- ═══════════════════════════════════════════════════════════════

-- Pardon (user_service_id = 1)
INSERT INTO service_courts_police (user_service_id, type, name, city, received) VALUES
(1, 'court',  'Toronto Court of Appeal', 'Toronto', 1),
(1, 'court',  'Courts of King''s Bench — Criminal', 'Winnipeg', 0),
(1, 'police', 'Agassiz Community Police', 'Agassiz', 0),
(1, 'police', 'ALEXANDRIA OPP', 'Alexandria', 0);

-- Criminal Rehab (user_service_id = 2)
INSERT INTO service_courts_police (user_service_id, type, name, city) VALUES
(2, 'court',  'Superior Court of Los Angeles County', 'Los Angeles'),
(2, 'court',  'Cook County Circuit Court', 'Chicago'),
(2, 'police', 'Los Angeles Police Department', 'Los Angeles'),
(2, 'police', 'Chicago Police Department', 'Chicago');

-- TRP (user_service_id = 3)
INSERT INTO service_courts_police (user_service_id, type, name, city) VALUES
(3, 'court',  'Maricopa County Superior Court', 'Phoenix'),
(3, 'police', 'Phoenix Police Department', 'Phoenix');

-- Expunging (user_service_id = 5)
INSERT INTO service_courts_police (user_service_id, type, name, city) VALUES
(5, 'court',  'Court of Queen''s Bench of Alberta', 'Calgary'),
(5, 'police', 'Calgary Police Service', 'Calgary');

-- Waiver (user_service_id = 6)
INSERT INTO service_courts_police (user_service_id, type, name, city) VALUES
(6, 'court',  'Provincial Court of Manitoba', 'Winnipeg'),
(6, 'police', 'Winnipeg Police Service', 'Winnipeg');

-- ═══════════════════════════════════════════════════════════════
-- EMAIL HISTORY
-- ═══════════════════════════════════════════════════════════════

-- Pardon emails (user_service_id = 1)
INSERT INTO service_email_history (user_service_id, email_number, sent_at, sent_by, subject) VALUES
(1, 6, '2025-07-03 12:28:00', 'Lev Tiutiunyk', 'CIF + CF (Clear CCR)'),
(1, 1, '2025-07-10 14:23:00', 'Lev Tiutiunyk', 'Wrong mailing address'),
(1, 1, '2025-07-10 14:24:00', 'Lev Tiutiunyk', 'Wrong mailing address'),
(1, 1, '2025-07-10 14:25:00', 'Lev Tiutiunyk', 'Wrong mailing address'),
(1, 0, '2025-09-17 08:11:00', 'Lev Tiutiunyk', 'Custom Update'),
(1, 0, '2025-09-17 08:35:00', 'Lev Tiutiunyk', 'Custom Update'),
(1, 1, '2025-09-22 11:23:00', 'Lev Tiutiunyk', 'LPRC forms to client'),
(1, 0, '2025-09-22 15:59:00', 'Lev Tiutiunyk', 'Custom Update'),
(1, 5, '2025-09-25 07:50:00', 'Lev Tiutiunyk', 'LAC MCSh Req');

-- Criminal Rehab emails (user_service_id = 2)
INSERT INTO service_email_history (user_service_id, email_number, sent_at, sent_by, subject) VALUES
(2, 6, '2025-06-15 09:00:00', 'Lev Tiutiunyk', 'CIF + CF (Clear CCR)'),
(2, 3, '2025-07-20 10:30:00', 'Lev Tiutiunyk', 'Criminal Record Received'),
(2, 0, '2025-08-05 14:15:00', 'Lev Tiutiunyk', 'Custom Update'),
(2, 2, '2025-09-10 11:00:00', 'Lev Tiutiunyk', 'Application Submitted');

-- TRP emails (user_service_id = 3)
INSERT INTO service_email_history (user_service_id, email_number, sent_at, sent_by, subject) VALUES
(3, 6, '2025-07-01 08:45:00', 'Lev Tiutiunyk', 'CIF + CF (Clear CCR)'),
(3, 0, '2025-08-12 16:00:00', 'Lev Tiutiunyk', 'Custom Update'),
(3, 4, '2025-09-30 09:20:00', 'Lev Tiutiunyk', 'Biometrics Instructions');

-- NEXUS emails (user_service_id = 4)
INSERT INTO service_email_history (user_service_id, email_number, sent_at, sent_by, subject) VALUES
(4, 1, '2025-08-01 10:00:00', 'Lev Tiutiunyk', 'NEXUS Application Started'),
(4, 0, '2025-09-15 13:30:00', 'Lev Tiutiunyk', 'Custom Update — Conditional Approval');

-- Expunging emails (user_service_id = 5)
INSERT INTO service_email_history (user_service_id, email_number, sent_at, sent_by, subject) VALUES
(5, 6, '2025-06-01 11:00:00', 'Lev Tiutiunyk', 'CIF + CF (Clear CCR)'),
(5, 0, '2025-07-15 09:45:00', 'Lev Tiutiunyk', 'Custom Update'),
(5, 7, '2025-08-20 14:30:00', 'Lev Tiutiunyk', 'Destruction Order Filed');

-- Waiver emails (user_service_id = 6)
INSERT INTO service_email_history (user_service_id, email_number, sent_at, sent_by, subject) VALUES
(6, 6, '2025-07-05 08:30:00', 'Lev Tiutiunyk', 'CIF + CF (Clear CCR)'),
(6, 0, '2025-08-10 11:20:00', 'Lev Tiutiunyk', 'Custom Update'),
(6, 0, '2025-09-18 15:45:00', 'Lev Tiutiunyk', 'Custom Update — DHS Confirmation'),
(6, 3, '2025-10-01 10:00:00', 'Lev Tiutiunyk', 'Waiver Mailed to DHS');

-- ═══════════════════════════════════════════════════════════════
-- SBC/PCC RECORDS (for TRP and Criminal Rehab)
-- ═══════════════════════════════════════════════════════════════

-- Criminal Rehab SBCs (user_service_id = 2)
INSERT INTO service_sbc_records (user_service_id, bc_name, ordered_cf, ordered_mo, order_date, exp_date, snd_exp_date, sort_order) VALUES
(2, 'FBI',     0, 0, '2025-09-17', '2025-09-24', '2025-09-28', 0),
(2, 'fdghh',   1, 0, '2025-09-16', '2025-09-09', '2025-09-08', 1),
(2, 'dgfchvj', 0, 1, '2025-09-03', '2025-09-10', '2025-09-08', 2);

-- TRP SBCs (user_service_id = 3)
INSERT INTO service_sbc_records (user_service_id, bc_name, ordered_cf, ordered_mo, order_date, exp_date, snd_exp_date, sort_order) VALUES
(3, 'FBI',           0, 0, '2025-08-10', '2025-08-17', '2025-08-20', 0),
(3, 'RCMP Ottawa',   1, 1, '2025-08-15', '2025-09-01', '2025-09-15', 1),
(3, 'UK ACRO',       0, 0, '2025-07-20', '2025-10-20', NULL,         2);

-- ═══════════════════════════════════════════════════════════════
-- CONVICTION SUMMARY (for TRP and Criminal Rehab)
-- ═══════════════════════════════════════════════════════════════

-- Criminal Rehab convictions (user_service_id = 2)
INSERT INTO service_conviction_records (user_service_id, cycle_num, received, imm, arrest_date, place, charge, disposition, notes, sort_order) VALUES
(2, 1, 1, 0, '2025-09-18', 'adsd',    'asda', 'asda', 'First offence — minor charge', 0),
(2, 2, 0, 1, '2024-03-12', 'Los Angeles', 'Possession under 30g', 'Conditional Discharge', NULL, 1),
(2, 3, 1, 0, '2023-07-05', 'Chicago',  'Impaired Driving', 'Fine $2,000 + 1yr probation', 'Completed probation successfully', 2);

-- TRP convictions (user_service_id = 3)
INSERT INTO service_conviction_records (user_service_id, cycle_num, received, imm, arrest_date, place, charge, disposition, notes, sort_order) VALUES
(3, 1, 1, 1, '2022-11-15', 'Phoenix', 'DUI — First Offence', 'Fine $1,500 + driving prohibition', NULL, 0),
(3, 2, 0, 0, '2023-05-20', 'Scottsdale', 'Theft under $5,000', 'Absolute Discharge', 'Eligible for record suspension', 1);

-- ═══════════════════════════════════════════════════════════════
-- NEXUS RECORDS (for NEXUS service)
-- ═══════════════════════════════════════════════════════════════

INSERT INTO service_nexus_records (user_service_id, application_status, ttp_account, interview_date, interview_location, conditional_approval_date, card_issued_date, card_expiry_date, nexus_card_number, notes) VALUES
(4, 'Conditional Approval', 'TTP-2025-44821', '2025-11-15', 'Peace Bridge Enrollment Center, Fort Erie', '2025-10-01', NULL, NULL, NULL,
 'Interview scheduled. Bring passport + PR card.');

-- ═══════════════════════════════════════════════════════════════
-- NEXUS BACKUP CODES
-- ═══════════════════════════════════════════════════════════════

INSERT INTO service_nexus_codes (user_service_id, backup_code, is_used, date_used, computer_name, used_by, browser, sort_order) VALUES
(4, 'gsdfgsdfgdsfg',    1, '2026-01-20', 'This one', 'Lev Tiutiunyk', 'Chrome', 0),
(4, '5236426546486848', 1, '2026-01-20', 'Irina',    'Irina P',       'Chrome', 1);
