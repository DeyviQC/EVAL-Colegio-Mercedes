-- Bootstrap only on the owned isolated server; no production credentials.
CREATE DATABASE IF NOT EXISTS eval_u3_test CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
GRANT ALL PRIVILEGES ON eval_u3_test.* TO 'eval_u1_migration'@'127.0.0.1';
GRANT SELECT ON eval_u3_test.* TO 'eval_u1_runtime'@'127.0.0.1';
GRANT UPDATE(last_ordinal) ON eval_u3_test.academic_write_guard TO 'eval_u1_runtime'@'127.0.0.1';
-- MySQL 8.4.9 locking reads require a locking-capable privilege in addition to SELECT.
-- These column grants never permit identity/scope ID changes or DELETE.
GRANT UPDATE(credential_status) ON eval_u3_test.retained_identities TO 'eval_u1_runtime'@'127.0.0.1';
GRANT UPDATE(is_active) ON eval_u3_test.instructional_entries TO 'eval_u1_runtime'@'127.0.0.1';
GRANT UPDATE(is_active) ON eval_u3_test.sections TO 'eval_u1_runtime'@'127.0.0.1';
GRANT UPDATE(state) ON eval_u3_test.student_enrollments TO 'eval_u1_runtime'@'127.0.0.1';
GRANT UPDATE(state) ON eval_u3_test.teaching_assignments TO 'eval_u1_runtime'@'127.0.0.1';
GRANT INSERT(name,name_key,start_on,end_on,state), UPDATE(state) ON eval_u3_test.academic_periods TO 'eval_u1_runtime'@'127.0.0.1';
GRANT INSERT(name,name_key,is_active), UPDATE(name,name_key,is_active) ON eval_u3_test.grades TO 'eval_u1_runtime'@'127.0.0.1';
GRANT INSERT(entity_type,period_id,entry_id,grade_id,section_id,enrollment_id,assignment_id,actor_id,event_type,previous_state,new_state,effective_on,operation_key,correlation_id,recorded_at,metadata) ON eval_u3_test.academic_lifecycle_events TO 'eval_u1_runtime'@'127.0.0.1';
