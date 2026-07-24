ALTER TABLE permissions
    ADD COLUMN can_import TINYINT(1) NOT NULL DEFAULT 0 AFTER can_export,
    ADD COLUMN can_upload TINYINT(1) NOT NULL DEFAULT 0 AFTER can_import,
    ADD COLUMN can_download TINYINT(1) NOT NULL DEFAULT 0 AFTER can_upload,
    ADD COLUMN can_reject TINYINT(1) NOT NULL DEFAULT 0 AFTER can_approve,
    ADD COLUMN can_assign TINYINT(1) NOT NULL DEFAULT 0 AFTER can_reject,
    ADD COLUMN can_manage TINYINT(1) NOT NULL DEFAULT 0 AFTER can_assign,
    ADD COLUMN can_print TINYINT(1) NOT NULL DEFAULT 0 AFTER can_manage,
    ADD COLUMN can_share TINYINT(1) NOT NULL DEFAULT 0 AFTER can_print,
    ADD COLUMN data_scope ENUM('OWN','TEAM','DEPARTMENT','OFFICE','ALL') NOT NULL DEFAULT 'OWN';

ALTER TABLE user_permissions
    ADD COLUMN can_import TINYINT(1) NULL AFTER can_export,
    ADD COLUMN can_upload TINYINT(1) NULL AFTER can_import,
    ADD COLUMN can_download TINYINT(1) NULL AFTER can_upload,
    ADD COLUMN can_reject TINYINT(1) NULL AFTER can_approve,
    ADD COLUMN can_assign TINYINT(1) NULL AFTER can_reject,
    ADD COLUMN can_manage TINYINT(1) NULL AFTER can_assign,
    ADD COLUMN can_print TINYINT(1) NULL AFTER can_manage,
    ADD COLUMN can_share TINYINT(1) NULL AFTER can_print,
    ADD COLUMN data_scope ENUM('OWN','TEAM','DEPARTMENT','OFFICE','ALL') NULL;

INSERT IGNORE INTO resources(resource_name,display_name,icon,route,sort_order,status) VALUES
('employees','Employees','fa fa-id-badge','/dashboard/employee-status',13,'Active'),
('attendance','Attendance','fa fa-calendar-check','/dashboard/my-profile',14,'Active'),
('profile','My Profile','fa fa-user-circle','/dashboard/my-profile',15,'Active'),
('documents','Documents','fa fa-file-shield','/dashboard/document-reminders',20,'Active'),
('audit_logs','Audit Logs','fa fa-history','/dashboard/audit-logs',30,'Active');

UPDATE resources SET route='/dashboard/users' WHERE resource_name='users';
UPDATE resources SET route='/dashboard/positions' WHERE resource_name='positions';
UPDATE resources SET route='/dashboard/resources' WHERE resource_name='resources';
UPDATE resources SET route='/dashboard/permissions' WHERE resource_name='permissions';
UPDATE resources SET route='/dashboard/reports' WHERE resource_name='reports';
UPDATE resources SET route='/dashboard/settings' WHERE resource_name='settings';

INSERT INTO permissions(position_id,resource_id,can_view,can_create,can_edit,can_delete,
 can_export,can_import,can_upload,can_download,can_approve,can_reject,can_assign,
 can_manage,can_print,can_share,data_scope)
SELECT 1,id,1,1,1,1,1,1,1,1,1,1,1,1,1,1,'ALL' FROM resources
ON DUPLICATE KEY UPDATE can_view=1,can_create=1,can_edit=1,can_delete=1,
 can_export=1,can_import=1,can_upload=1,can_download=1,can_approve=1,
 can_reject=1,can_assign=1,can_manage=1,can_print=1,can_share=1,data_scope='ALL';

INSERT INTO permissions(position_id,resource_id,can_view,data_scope)
SELECT p.id,r.id,1,'OWN' FROM positions p JOIN resources r ON r.resource_name IN('profile','attendance')
WHERE p.status='Active'
ON DUPLICATE KEY UPDATE can_view=1,data_scope=IF(permissions.data_scope='ALL','ALL','OWN');

INSERT INTO permissions(position_id,resource_id,can_view,can_create,can_edit,can_delete,can_assign,can_manage,data_scope)
SELECT p.id,r.id,1,1,1,1,1,1,'ALL' FROM positions p JOIN resources r ON r.resource_name='employees'
WHERE p.position_name IN('Admin','HR')
ON DUPLICATE KEY UPDATE can_view=1,can_create=1,can_edit=1,can_delete=1,can_assign=1,can_manage=1,data_scope='ALL';

UPDATE resources SET status='Inactive' WHERE resource_name='emp_status_report';

CREATE TABLE IF NOT EXISTS audit_logs (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 user_id INT UNSIGNED NULL, resource VARCHAR(100) NOT NULL, action VARCHAR(50) NOT NULL,
 record_id VARCHAR(100) NULL, old_value JSON NULL, new_value JSON NULL,
 ip_address VARCHAR(45) NULL, user_agent VARCHAR(500) NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_audit_user_date(user_id,created_at), INDEX idx_audit_resource_action(resource,action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE audit_logs
    ADD COLUMN employee_id INT UNSIGNED NULL AFTER user_id,
    ADD COLUMN status ENUM('Allowed','Denied') NOT NULL DEFAULT 'Allowed' AFTER user_agent,
    ADD INDEX idx_audit_employee_date(employee_id,created_at),
    ADD INDEX idx_audit_status(status);

CREATE TABLE IF NOT EXISTS login_attempts (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 identifier VARCHAR(190) NOT NULL, ip_address VARCHAR(45) NOT NULL,
 succeeded TINYINT(1) NOT NULL DEFAULT 0, attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_login_attempt(identifier,ip_address,attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
