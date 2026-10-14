-- Add optional candidate feedback; safe to run again on MySQL/MariaDB.
SET @candidate_feedback_sql = (
 SELECT IF(COUNT(*) = 0,
  'ALTER TABLE application ADD COLUMN candidate_feedback TEXT NULL AFTER feedback',
  'SELECT 1')
 FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'application'
 AND COLUMN_NAME = 'candidate_feedback'
);
PREPARE candidate_feedback_migration FROM @candidate_feedback_sql;
EXECUTE candidate_feedback_migration;
DEALLOCATE PREPARE candidate_feedback_migration;
