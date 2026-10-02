-- Additional context for role-specific pre-offer invitations. Existing links stay valid.
SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='employee_onboarding_invites' AND COLUMN_NAME='selected_role')=0,'ALTER TABLE employee_onboarding_invites ADD COLUMN selected_role VARCHAR(64) NULL','SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='employee_onboarding_invites' AND COLUMN_NAME='candidate_name')=0,'ALTER TABLE employee_onboarding_invites ADD COLUMN candidate_name VARCHAR(250) NULL','SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
