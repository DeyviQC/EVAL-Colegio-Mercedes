-- EVAL-NSM: crear una base nueva para el modelo conectado.
-- Las tablas eval_* se crean por app/database.php al abrir login.php.
-- No importar el esquema del prototipo anterior.
CREATE DATABASE IF NOT EXISTS eval_nsm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- Configurar config/local.php con esta base y un usuario autorizado.

