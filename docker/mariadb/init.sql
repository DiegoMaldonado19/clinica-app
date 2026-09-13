-- La base de pruebas. Existe para que `pest` no corra contra la de desarrollo.
CREATE DATABASE IF NOT EXISTS clinica_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON clinica_test.* TO 'clinica'@'%';
