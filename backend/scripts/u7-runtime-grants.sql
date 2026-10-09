CREATE DATABASE IF NOT EXISTS eval_u7_test CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
GRANT ALL PRIVILEGES ON eval_u7_test.* TO 'eval_u1_migration'@'127.0.0.1';
GRANT SELECT ON eval_u7_test.* TO 'eval_u1_runtime'@'127.0.0.1';
GRANT UPDATE(last_ordinal) ON eval_u7_test.academic_write_guard TO 'eval_u1_runtime'@'127.0.0.1';
GRANT UPDATE(credential_status) ON eval_u7_test.retained_identities TO 'eval_u1_runtime'@'127.0.0.1';
GRANT INSERT(name,name_key,start_on,end_on,state), UPDATE(state) ON eval_u7_test.academic_periods TO 'eval_u1_runtime'@'127.0.0.1';
GRANT INSERT(name,name_key,kind,is_active), UPDATE(name,name_key,is_active) ON eval_u7_test.instructional_entries TO 'eval_u1_runtime'@'127.0.0.1';
GRANT INSERT(name,name_key,is_active), UPDATE(name,name_key,is_active) ON eval_u7_test.grades TO 'eval_u1_runtime'@'127.0.0.1';
GRANT INSERT(name,name_key,grade_id,is_active), UPDATE(name,name_key,is_active) ON eval_u7_test.sections TO 'eval_u1_runtime'@'127.0.0.1';
GRANT INSERT(entity_type,period_id,entry_id,grade_id,section_id,enrollment_id,assignment_id,actor_id,event_type,previous_state,new_state,effective_on,operation_key,correlation_id,recorded_at,metadata) ON eval_u7_test.academic_lifecycle_events TO 'eval_u1_runtime'@'127.0.0.1';
GRANT INSERT, UPDATE ON eval_u7_test.local_sessions TO 'eval_u1_runtime'@'127.0.0.1';
GRANT INSERT, UPDATE ON eval_u7_test.local_login_limits TO 'eval_u1_runtime'@'127.0.0.1';

GRANT INSERT(student_id,academic_period_id,grade_id,section_id,state,effective_from,effective_until,operational_start_key), UPDATE(state,effective_until,operational_end_key) ON eval_u7_test.student_enrollments TO 'eval_u1_runtime'@'127.0.0.1';

GRANT INSERT(teacher_id,academic_period_id,instructional_entry_id,grade_id,section_id,state,effective_from,effective_until), UPDATE(state,effective_until,operational_start_key,operational_end_key) ON eval_u7_test.teaching_assignments TO 'eval_u1_runtime'@'127.0.0.1';
