-- Protecao contra forca bruta no login
-- Execute uma vez caso prefira aplicar o schema manualmente.

SET @schema_name = DATABASE();

SET @sql = IF(
    EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @schema_name
          AND TABLE_NAME = 'usuarios'
          AND COLUMN_NAME = 'tentativas_login'
    ),
    'SELECT 1',
    'ALTER TABLE usuarios ADD COLUMN tentativas_login INT NOT NULL DEFAULT 0'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @schema_name
          AND TABLE_NAME = 'usuarios'
          AND COLUMN_NAME = 'bloqueado_ate'
    ),
    'SELECT 1',
    'ALTER TABLE usuarios ADD COLUMN bloqueado_ate DATETIME NULL DEFAULT NULL'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
