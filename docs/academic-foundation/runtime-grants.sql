-- Run as an authorized DBA after Laravel migrations.
-- Replace database/account names with the actual configured identities.
-- Set a private random password separately; never copy a demo password here.
GRANT SELECT, INSERT, UPDATE ON eval.users TO 'eval_runtime'@'localhost';
GRANT SELECT, UPDATE ON eval.academic_write_guard TO 'eval_runtime'@'localhost';
GRANT SELECT, INSERT, UPDATE ON eval.academic_periods TO 'eval_runtime'@'localhost';
GRANT SELECT, INSERT, UPDATE ON eval.instructional_entries TO 'eval_runtime'@'localhost';
GRANT SELECT, INSERT, UPDATE ON eval.grades TO 'eval_runtime'@'localhost';
GRANT SELECT, INSERT, UPDATE ON eval.sections TO 'eval_runtime'@'localhost';
GRANT SELECT, INSERT, UPDATE ON eval.student_enrollments TO 'eval_runtime'@'localhost';
GRANT SELECT, INSERT, UPDATE ON eval.teaching_assignments TO 'eval_runtime'@'localhost';
-- MySQL SELECT ... FOR UPDATE needs an additional locking-capable privilege.
-- Immutable-route triggers protect relationship changes on these reference tables.
GRANT SELECT, INSERT, UPDATE ON eval.activity_references TO 'eval_runtime'@'localhost';
GRANT SELECT, INSERT, UPDATE ON eval.submission_references TO 'eval_runtime'@'localhost';
GRANT SELECT, INSERT ON eval.academic_lifecycle_events TO 'eval_runtime'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON eval.sessions TO 'eval_runtime'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON eval.cache TO 'eval_runtime'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON eval.cache_locks TO 'eval_runtime'@'localhost';
-- Grant additional framework queue/reset tables only if the deployment enables them.
GRANT SELECT, INSERT, UPDATE ON eval.activity_settings TO 'eval_runtime'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON eval.educational_materials TO 'eval_runtime'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON eval.submission_files TO 'eval_runtime'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON eval.submission_assessments TO 'eval_runtime'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON eval.education_notifications TO 'eval_runtime'@'localhost';
GRANT SELECT, INSERT ON eval.education_events TO 'eval_runtime'@'localhost';
