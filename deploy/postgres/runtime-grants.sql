BEGIN;
REVOKE CREATE ON SCHEMA public FROM PUBLIC;
GRANT USAGE ON SCHEMA public TO mdr_runtime;

GRANT SELECT ON migrations, roles TO mdr_runtime;

GRANT SELECT, INSERT ON user_roles, debt_verifications, audit_logs, webhook_events,
    allocation_runs, donation_allocations, shop_payment_lines, financial_events,
    funding_receipt_reports, settlement_queue, settlement_transfers, settlement_actions
    TO mdr_runtime;

GRANT SELECT, INSERT, UPDATE ON users, regions, cities, localities, shops, shop_documents,
    shop_memberships, shop_debt_requests, beneficiary_profiles, debt_cases, documents, donations,
    payment_transactions, shop_payments, refunds, case_funding_reports, settlement_response_heads
    TO mdr_runtime;

GRANT SELECT, UPDATE ON financial_control TO mdr_runtime;

GRANT SELECT, INSERT, UPDATE, DELETE ON sessions, password_reset_tokens, personal_access_tokens,
    cache, cache_locks, jobs, job_batches, failed_jobs TO mdr_runtime;

GRANT USAGE ON SEQUENCE jobs_id_seq, failed_jobs_id_seq, personal_access_tokens_id_seq TO mdr_runtime;
COMMIT;
