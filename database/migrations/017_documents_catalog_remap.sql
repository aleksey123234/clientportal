-- Migration 017: Remap documents.doc_key to new catalog; delete obsolete slots
-- Run: mysql -u root -p mysql_clients_portal < database/migrations/017_documents_catalog_remap.sql

USE mysql_clients_portal;

-- ── Renames (CR) ────────────────────────────────────────────
UPDATE documents SET doc_key = 'cr-final-forms',        title = 'Final Forms (IMMs + RLS)' WHERE doc_key = 'cr-imm-forms';
UPDATE documents SET doc_key = 'cr-personal-statement', title = 'Personal Statement'       WHERE doc_key = 'cr-rehab-letter';
UPDATE documents SET title = 'Employment Letter'                      WHERE doc_key = 'cr-employment-letter';
UPDATE documents SET title = 'Police Clearance Certificate (PCC)'     WHERE doc_key = 'cr-police-cert';

-- ── Renames (TRP) ───────────────────────────────────────────
UPDATE documents SET doc_key = 'trp-final-forms',        title = 'Final Forms (IMMs + RLS)' WHERE doc_key = 'trp-imm-forms';
UPDATE documents SET doc_key = 'trp-employment-letter',  title = 'Employment Letter'       WHERE doc_key = 'trp-support-letter';
UPDATE documents SET title = 'Police Clearance Certificate (PCC)'      WHERE doc_key = 'trp-police-cert';
UPDATE documents SET title = 'Identity Documents (Passport)'           WHERE doc_key = 'trp-id';

-- ── Renames (Waiver) ────────────────────────────────────────
UPDATE documents SET doc_key = 'waiver-g28-form', title = 'G-28 Form'        WHERE doc_key = 'waiver-i192-form';
UPDATE documents SET title = 'Employment Letter'                            WHERE doc_key = 'waiver-employment';

-- ── Renames (Pardon) ────────────────────────────────────────
UPDATE documents SET doc_key = 'pardon-ccr',  title = 'Canadian Criminal Record Check (CCR)' WHERE doc_key = 'pardon-rcmp';
UPDATE documents SET doc_key = 'pardon-mbf',  title = 'MBF (Measure Benefit Form)'           WHERE doc_key = 'pardon-measurable-benefit';
UPDATE documents SET doc_key = 'pardon-lprc', title = 'LPRC (Local Police Records Check Form)' WHERE doc_key = 'pardon-local-police';
UPDATE documents SET title = 'Military Conduct Sheet Consent Form (if applicable)' WHERE doc_key = 'pardon-military';

-- ── Renames (Nexus / Expg titles kept where id remains) ─────
UPDATE documents SET title = 'Identity Documents' WHERE doc_key = 'nexus-id';
UPDATE documents SET title = 'Identity Documents' WHERE doc_key = 'exp-id';

-- ── Delete obsolete slots ───────────────────────────────────
DELETE FROM documents WHERE doc_key IN (
    'cr-court-records',
    'trp-court-records',
    'nexus-pardon-proof',
    'nexus-waiver-proof',
    'nexus-application',
    'nexus-photos',
    'exp-court-records',
    'exp-police-cert',
    'exp-petition',
    'exp-rehab-evidence',
    'waiver-court-records',
    'waiver-police-cert',
    'waiver-photos',
    'pardon-court-info',
    'pardon-photos',
    'pardon-lprc-form',
    'pardon-pbc-schedule1'
);
