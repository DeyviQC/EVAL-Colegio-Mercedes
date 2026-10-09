-- Same owned isolated server; existing account secrets remain outside Git.
CREATE DATABASE IF NOT EXISTS eval_u2_test CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
GRANT ALL PRIVILEGES ON eval_u2_test.* TO 'eval_u1_migration'@'127.0.0.1';
GRANT SELECT ON eval_u2_test.* TO 'eval_u1_runtime'@'127.0.0.1';
