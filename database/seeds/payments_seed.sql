-- DEV ONLY — do not run on production.
-- Hardcoded user_id = 1 and plan IDs. Safe only on local/dev DBs.
-- Seed payments data for all 6 services
-- User 1, starting June 2025, monthly installments
-- Plan IDs: 3=Pardon(12), 4=CrimRehab(12), 5=TRP(16), 6=NEXUS(12), 7=Expunging(12), 8=Waiver(12)
-- Per-month amounts: Pardon=100, CrimRehab=166.67, TRP=93.75, NEXUS=41.67, Expunging=66.67, Waiver=150

-- ═══ PARDON (plan 3, $1200 / 12 = $100/mo) ═══
INSERT INTO payments (user_id, payment_plan_id, installment_number, amount, due_date, paid_date, status, method, reference_number) VALUES
(1, 3, 1,  100.00, '2025-06-01', '2025-06-01', 'paid',    'credit_card', 'PAR-001'),
(1, 3, 2,  100.00, '2025-07-01', '2025-07-03', 'paid',    'debit',       'PAR-002'),
(1, 3, 3,  100.00, '2025-08-01', '2025-08-01', 'paid',    'e_transfer',  'PAR-003'),
(1, 3, 4,  100.00, '2025-09-01', '2025-09-02', 'paid',    'credit_card', 'PAR-004'),
(1, 3, 5,  100.00, '2025-10-01', NULL,         'missed',  NULL,          NULL),
(1, 3, 6,  100.00, '2025-11-01', '2025-11-01', 'paid',    'cheque',      'PAR-006'),
(1, 3, 7,  100.00, '2025-12-01', '2025-12-01', 'paid',    'credit_card', 'PAR-007'),
(1, 3, 8,  100.00, '2026-01-01', '2026-01-02', 'paid',    'e_transfer',  'PAR-008'),
(1, 3, 9,  100.00, '2026-02-01', NULL,         'pending', NULL,          NULL),
(1, 3, 10, 100.00, '2026-03-01', NULL,         'pending', NULL,          NULL),
(1, 3, 11, 100.00, '2026-04-01', NULL,         'pending', NULL,          NULL),
(1, 3, 12, 100.00, '2026-05-01', NULL,         'pending', NULL,          NULL);

-- ═══ CRIMINAL REHAB (plan 4, $2000 / 12 = $166.67/mo) ═══
INSERT INTO payments (user_id, payment_plan_id, installment_number, amount, due_date, paid_date, status, method, reference_number) VALUES
(1, 4, 1,  166.67, '2025-06-01', '2025-06-01', 'paid',    'credit_card', 'CR-001'),
(1, 4, 2,  166.67, '2025-07-01', '2025-07-03', 'paid',    'debit',       'CR-002'),
(1, 4, 3,  166.67, '2025-08-01', '2025-08-01', 'paid',    'e_transfer',  'CR-003'),
(1, 4, 4,  166.67, '2025-09-01', '2025-09-02', 'paid',    'credit_card', 'CR-004'),
(1, 4, 5,  166.67, '2025-10-01', NULL,         'missed',  NULL,          NULL),
(1, 4, 6,  166.67, '2025-11-01', '2025-11-01', 'paid',    'cheque',      'CR-006'),
(1, 4, 7,  166.67, '2025-12-01', '2025-12-01', 'paid',    'credit_card', 'CR-007'),
(1, 4, 8,  166.62, '2026-01-01', '2026-01-02', 'paid',    'e_transfer',  'CR-008'),
(1, 4, 9,  166.67, '2026-02-01', NULL,         'pending', NULL,          NULL),
(1, 4, 10, 166.67, '2026-03-01', NULL,         'pending', NULL,          NULL),
(1, 4, 11, 166.67, '2026-04-01', NULL,         'pending', NULL,          NULL),
(1, 4, 12, 166.67, '2026-05-01', NULL,         'pending', NULL,          NULL);

-- ═══ TRP (plan 5, $375 / 4 = $93.75/mo — only months 13-16) ═══
INSERT INTO payments (user_id, payment_plan_id, installment_number, amount, due_date, paid_date, status, method, reference_number) VALUES
(1, 5, 13, 93.75, '2026-06-01', NULL,         'pending', NULL,          NULL),
(1, 5, 14, 93.75, '2026-07-01', NULL,         'pending', NULL,          NULL),
(1, 5, 15, 93.75, '2026-08-01', NULL,         'pending', NULL,          NULL),
(1, 5, 16, 93.75, '2026-09-01', NULL,         'pending', NULL,          NULL);

-- ═══ NEXUS (plan 6, $500 / 12 = $41.67/mo) ═══
INSERT INTO payments (user_id, payment_plan_id, installment_number, amount, due_date, paid_date, status, method, reference_number) VALUES
(1, 6, 1,  41.67, '2025-06-01', '2025-06-01', 'paid',    'credit_card', 'NX-001'),
(1, 6, 2,  41.67, '2025-07-01', '2025-07-03', 'paid',    'debit',       'NX-002'),
(1, 6, 3,  41.67, '2025-08-01', '2025-08-01', 'paid',    'e_transfer',  'NX-003'),
(1, 6, 4,  41.67, '2025-09-01', '2025-09-02', 'paid',    'credit_card', 'NX-004'),
(1, 6, 5,  41.67, '2025-10-01', NULL,         'missed',  NULL,          NULL),
(1, 6, 6,  41.67, '2025-11-01', '2025-11-01', 'paid',    'cheque',      'NX-006'),
(1, 6, 7,  41.67, '2025-12-01', '2025-12-01', 'paid',    'credit_card', 'NX-007'),
(1, 6, 8,  41.62, '2026-01-01', '2026-01-02', 'paid',    'e_transfer',  'NX-008'),
(1, 6, 9,  41.67, '2026-02-01', NULL,         'pending', NULL,          NULL),
(1, 6, 10, 41.67, '2026-03-01', NULL,         'pending', NULL,          NULL),
(1, 6, 11, 41.67, '2026-04-01', NULL,         'pending', NULL,          NULL),
(1, 6, 12, 41.67, '2026-05-01', NULL,         'pending', NULL,          NULL);

-- ═══ EXPUNGING (plan 7, $800 / 12 = $66.67/mo) ═══
INSERT INTO payments (user_id, payment_plan_id, installment_number, amount, due_date, paid_date, status, method, reference_number) VALUES
(1, 7, 1,  66.67, '2025-06-01', '2025-06-01', 'paid',    'credit_card', 'EX-001'),
(1, 7, 2,  66.67, '2025-07-01', '2025-07-03', 'paid',    'debit',       'EX-002'),
(1, 7, 3,  66.67, '2025-08-01', '2025-08-01', 'paid',    'e_transfer',  'EX-003'),
(1, 7, 4,  66.67, '2025-09-01', '2025-09-02', 'paid',    'credit_card', 'EX-004'),
(1, 7, 5,  66.67, '2025-10-01', NULL,         'missed',  NULL,          NULL),
(1, 7, 6,  66.67, '2025-11-01', '2025-11-01', 'paid',    'cheque',      'EX-006'),
(1, 7, 7,  66.67, '2025-12-01', '2025-12-01', 'paid',    'credit_card', 'EX-007'),
(1, 7, 8,  66.62, '2026-01-01', '2026-01-02', 'paid',    'e_transfer',  'EX-008'),
(1, 7, 9,  66.67, '2026-02-01', NULL,         'pending', NULL,          NULL),
(1, 7, 10, 66.67, '2026-03-01', NULL,         'pending', NULL,          NULL),
(1, 7, 11, 66.67, '2026-04-01', NULL,         'pending', NULL,          NULL),
(1, 7, 12, 66.67, '2026-05-01', NULL,         'pending', NULL,          NULL);

-- ═══ WAIVER (plan 8, $1800 / 12 = $150/mo) ═══
INSERT INTO payments (user_id, payment_plan_id, installment_number, amount, due_date, paid_date, status, method, reference_number) VALUES
(1, 8, 1,  150.00, '2025-06-01', '2025-06-01', 'paid',    'credit_card', 'WV-001'),
(1, 8, 2,  150.00, '2025-07-01', '2025-07-03', 'paid',    'debit',       'WV-002'),
(1, 8, 3,  150.00, '2025-08-01', '2025-08-01', 'paid',    'e_transfer',  'WV-003'),
(1, 8, 4,  150.00, '2025-09-01', '2025-09-02', 'paid',    'credit_card', 'WV-004'),
(1, 8, 5,  150.00, '2025-10-01', NULL,         'missed',  NULL,          NULL),
(1, 8, 6,  150.00, '2025-11-01', '2025-11-01', 'paid',    'cheque',      'WV-006'),
(1, 8, 7,  150.00, '2025-12-01', '2025-12-01', 'paid',    'credit_card', 'WV-007'),
(1, 8, 8,  150.00, '2026-01-01', '2026-01-02', 'paid',    'e_transfer',  'WV-008'),
(1, 8, 9,  150.00, '2026-02-01', NULL,         'pending', NULL,          NULL),
(1, 8, 10, 150.00, '2026-03-01', NULL,         'pending', NULL,          NULL),
(1, 8, 11, 150.00, '2026-04-01', NULL,         'pending', NULL,          NULL),
(1, 8, 12, 150.00, '2026-05-01', NULL,         'pending', NULL,          NULL);

-- ═══ NSF FEES (on missed installment 5 = October 2025) ═══
-- Find payment IDs for installment 5 of Pardon and CrimRehab and add NSF
-- Will be inserted after knowing the payment IDs

-- ═══ EXTRA SERVICES ═══
INSERT INTO user_extra_services (user_id, service_cost_id, amount, status, notes) VALUES
(1, 8,  75.00, 'completed', 'LPRC processing completed'),
(1, 9, 350.00, 'active',    'Fresh Start Program — in progress'),
(1, 10, 150.00, 'active',   'Measurable Benefit Filing');
