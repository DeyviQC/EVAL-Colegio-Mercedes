-- Existing owned disposable MySQL only. No institutional data.
CREATE DATABASE IF NOT EXISTS eval_auth_test CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
GRANT ALL PRIVILEGES ON eval_auth_test.* TO 'eval_u1_migration'@'127.0.0.1';
GRANT SELECT ON eval_auth_test.* TO 'eval_u1_runtime'@'127.0.0.1';
GRANT INSERT, UPDATE ON eval_auth_test.local_sessions TO 'eval_u1_runtime'@'127.0.0.1';
GRANT INSERT, UPDATE ON eval_auth_test.local_login_limits TO 'eval_u1_runtime'@'127.0.0.1';
-- Credential and role writes are deliberately unavailable to runtime.
-- Minimal U3 protocol integration fixture; no academic HTTP commands are registered.
GRANT UPDATE(last_ordinal) ON eval_auth_test.academic_write_guard TO 'eval_u1_runtime'@'127.0.0.1';
GRANT UPDATE(credential_status) ON eval_auth_test.retained_identities TO 'eval_u1_runtime'@'127.0.0.1';
GRANT UPDATE(name,name_key,is_active) ON eval_auth_test.grades TO 'eval_u1_runtime'@'127.0.0.1';
GRANT INSERT(entity_type,period_id,entry_id,grade_id,section_id,enrollment_id,assignment_id,actor_id,event_type,previous_state,new_state,effective_on,operation_key,correlation_id,recorded_at,metadata) ON eval_auth_test.academic_lifecycle_events TO 'eval_u1_runtime'@'127.0.0.1';
