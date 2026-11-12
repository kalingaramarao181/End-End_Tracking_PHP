-- Apply after candidate_profile_attendance. Repeatable MySQL 5.7+/MariaDB.
SET @tf_sql=IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='teamflow_weekly_timesheets' AND column_name='revision')=0,'ALTER TABLE teamflow_weekly_timesheets ADD COLUMN revision INT UNSIGNED NOT NULL DEFAULT 0, ADD COLUMN task_issue_id INT NULL, ADD COLUMN hours_override_json LONGTEXT NULL, ADD COLUMN submitted_at DATETIME NULL, ADD COLUMN reviewed_at DATETIME NULL, ADD COLUMN reviewed_by INT UNSIGNED NULL, ADD COLUMN review_note TEXT NULL','SELECT 1');
PREPARE tf_statement FROM @tf_sql; EXECUTE tf_statement; DEALLOCATE PREPARE tf_statement;
CREATE TABLE IF NOT EXISTS teamflow_timesheet_changes (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 timesheet_id INT NOT NULL, actor_id INT UNSIGNED NOT NULL,
 before_json LONGTEXT NULL, after_json LONGTEXT NOT NULL,
 reason VARCHAR(1000) NOT NULL, created_at DATETIME NOT NULL,
 KEY idx_tf_sheet_changes(timesheet_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Candidate chat/notifications use existing E2E conversations and membership checks.
INSERT IGNORE INTO permissions(position_id,resource_id,can_view,can_create,can_edit,can_delete,data_scope)
SELECT p.id,r.id,1,1,1,1,'OWN' FROM positions p JOIN resources r ON r.resource_name='chat' WHERE LOWER(p.position_name)='candidate';
INSERT IGNORE INTO permissions(position_id,resource_id,can_view,can_edit,data_scope)
SELECT p.id,r.id,1,1,'OWN' FROM positions p JOIN resources r ON r.resource_name='chat_notifications' WHERE LOWER(p.position_name)='candidate';
-- Developers receive scoped review visibility; approval remains separately configurable.
INSERT IGNORE INTO permissions(position_id,resource_id,can_view,can_export,data_scope)
SELECT p.id,r.id,1,1,'ALL' FROM positions p JOIN resources r ON r.resource_name='teamflow_timesheets' WHERE LOWER(p.position_name) LIKE '%develop%';
INSERT IGNORE INTO permissions(position_id,resource_id,can_view,can_edit,data_scope)
SELECT p.id,r.id,1,1,'OWN' FROM positions p JOIN resources r ON r.resource_name='chat_notifications' WHERE p.id IN(1,2) OR LOWER(p.position_name) LIKE '%develop%';
INSERT IGNORE INTO permissions(position_id,resource_id,can_view,can_create,data_scope)
SELECT p.id,r.id,1,1,'OWN' FROM positions p JOIN resources r ON r.resource_name='chat' WHERE p.id IN(1,2) OR LOWER(p.position_name) LIKE '%develop%';
