-- Local isolated-server bootstrap only; never run against an institutional server.
REVOKE ALL PRIVILEGES, GRANT OPTION FROM 'eval_u1_runtime'@'127.0.0.1';
GRANT SELECT, INSERT (credential_status, deactivated_at), UPDATE (credential_status, deactivated_at) ON eval_u1_test.retained_identities TO 'eval_u1_runtime'@'127.0.0.1';
GRANT SELECT, UPDATE (last_ordinal) ON eval_u1_test.academic_write_guard TO 'eval_u1_runtime'@'127.0.0.1';
GRANT SELECT, INSERT (retained_identity_id, reference_context) ON eval_u1_test.academic_identity_references TO 'eval_u1_runtime'@'127.0.0.1';
GRANT SELECT ON eval_u1_test.migrations TO 'eval_u1_runtime'@'127.0.0.1';
