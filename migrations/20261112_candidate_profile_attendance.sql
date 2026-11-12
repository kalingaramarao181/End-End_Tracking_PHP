-- Repeatable on MySQL 5.7+ / MariaDB; apply after the TeamFlow integration.
SET @tf_sql=IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='teamflow_employee_work_status' AND column_name='login_utc')=0,'ALTER TABLE teamflow_employee_work_status ADD COLUMN login_utc DATETIME NULL','SELECT 1');
PREPARE tf_statement FROM @tf_sql; EXECUTE tf_statement; DEALLOCATE PREPARE tf_statement;
SET @tf_sql=IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='teamflow_employee_work_status' AND column_name='logout_utc')=0,'ALTER TABLE teamflow_employee_work_status ADD COLUMN logout_utc DATETIME NULL','SELECT 1');
PREPARE tf_statement FROM @tf_sql; EXECUTE tf_statement; DEALLOCATE PREPARE tf_statement;
SET @tf_sql=IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='teamflow_employee_work_status' AND column_name='clock_source')=0,'ALTER TABLE teamflow_employee_work_status ADD COLUMN clock_source VARCHAR(20) NOT NULL DEFAULT ''legacy''','SELECT 1');
PREPARE tf_statement FROM @tf_sql; EXECUTE tf_statement; DEALLOCATE PREPARE tf_statement;
SET @tf_sql=IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='teamflow_employee_work_status' AND column_name='progress_percent')=0,'ALTER TABLE teamflow_employee_work_status ADD COLUMN progress_percent DECIMAL(5,2) NOT NULL DEFAULT 0','SELECT 1');
PREPARE tf_statement FROM @tf_sql; EXECUTE tf_statement; DEALLOCATE PREPARE tf_statement;
SET @tf_sql=IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='teamflow_employee_work_status' AND column_name='blockers')=0,'ALTER TABLE teamflow_employee_work_status ADD COLUMN blockers TEXT NULL','SELECT 1');
PREPARE tf_statement FROM @tf_sql; EXECUTE tf_statement; DEALLOCATE PREPARE tf_statement;
SET @tf_sql=IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='teamflow_weekly_timesheets' AND column_name='source_type')=0,'ALTER TABLE teamflow_weekly_timesheets ADD COLUMN source_type VARCHAR(20) NOT NULL DEFAULT ''manual''','SELECT 1');
PREPARE tf_statement FROM @tf_sql; EXECUTE tf_statement; DEALLOCATE PREPARE tf_statement;
CREATE TABLE IF NOT EXISTS teamflow_timesheet_worklogs (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 session_id INT NOT NULL, user_id INT UNSIGNED NOT NULL, timesheet_id INT NOT NULL,
 work_date DATE NOT NULL, duration_seconds INT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL,
 UNIQUE KEY uq_tf_session_day(session_id,work_date),
 KEY idx_tf_worklog_sheet(timesheet_id), KEY idx_tf_worklog_user_date(user_id,work_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
