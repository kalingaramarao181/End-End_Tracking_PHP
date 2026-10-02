-- Apply before deploying candidate collection code. Existing payroll JSON remains unchanged.
SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='employees' AND COLUMN_NAME='candidate_collection')=0,'ALTER TABLE employees ADD COLUMN candidate_collection LONGTEXT NULL AFTER payroll_profile','SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;