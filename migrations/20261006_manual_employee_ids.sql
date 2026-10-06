-- Run before deploying the updated employee APIs. Assigned IDs and unique indexes are preserved.
SET @employee_id_length = (SELECT GREATEST(50,CHARACTER_MAXIMUM_LENGTH) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='employees' AND COLUMN_NAME='employee_id');
SET @employee_id_charset = (SELECT CHARACTER_SET_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='employees' AND COLUMN_NAME='employee_id');
SET @employee_id_collation = (SELECT COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='employees' AND COLUMN_NAME='employee_id');
SET @employee_id_sql = CONCAT('ALTER TABLE employees MODIFY COLUMN employee_id VARCHAR(', @employee_id_length, ') CHARACTER SET ', @employee_id_charset, ' COLLATE ', @employee_id_collation, ' NULL DEFAULT NULL');
PREPARE employee_id_statement FROM @employee_id_sql;
EXECUTE employee_id_statement;
DEALLOCATE PREPARE employee_id_statement;
UPDATE employees SET employee_id=NULL WHERE TRIM(employee_id)='';
