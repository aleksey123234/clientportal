-- Migration: 016_add_moneris_columns
-- Adds Moneris transaction tracking columns to the payments table.
-- moneris_order_id  — the unique order ID we send to Moneris (mpg_portal_<payment_id>_<timestamp>)
-- moneris_txn_id    — the TxnNumber returned by Moneris on success (used for refunds / audit)
-- moneris_response  — raw response code returned by Moneris (e.g. "027" = approved, "null" = declined)

ALTER TABLE payments
    ADD COLUMN moneris_order_id  VARCHAR(64)   NULL DEFAULT NULL AFTER reference_number,
    ADD COLUMN moneris_txn_id    VARCHAR(64)   NULL DEFAULT NULL AFTER moneris_order_id,
    ADD COLUMN moneris_response  VARCHAR(10)   NULL DEFAULT NULL AFTER moneris_txn_id;
