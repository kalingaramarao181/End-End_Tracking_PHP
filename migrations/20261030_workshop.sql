-- Workshop: additive, repeatable migration for MySQL 5.7+/MariaDB.
CREATE TABLE IF NOT EXISTS workshop_meetings (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 meeting_date DATE NOT NULL,
 position_id INT NOT NULL,
 topic VARCHAR(200) NOT NULL,
 presenter_id INT UNSIGNED NOT NULL,
 remarks TEXT NOT NULL,
 created_by INT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 deleted_at DATETIME NULL,
 INDEX idx_workshop_date (deleted_at,meeting_date,id),
 INDEX idx_workshop_team (position_id,meeting_date),
 INDEX idx_workshop_presenter (presenter_id),
 INDEX idx_workshop_creator (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS workshop_attendees (
 workshop_id BIGINT UNSIGNED NOT NULL,
 user_id INT UNSIGNED NOT NULL,
 PRIMARY KEY (workshop_id,user_id),
 INDEX idx_workshop_attendee (user_id,workshop_id),
 CONSTRAINT fk_workshop_attendees_meeting FOREIGN KEY (workshop_id) REFERENCES workshop_meetings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS workshop_photos (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 workshop_id BIGINT UNSIGNED NOT NULL,
 storage_name VARCHAR(100) NOT NULL,
 original_name VARCHAR(190) NOT NULL,
 mime_type VARCHAR(40) NOT NULL,
 file_size INT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL,
 INDEX idx_workshop_photos_meeting (workshop_id),
 CONSTRAINT fk_workshop_photos_meeting FOREIGN KEY (workshop_id) REFERENCES workshop_meetings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO resources(resource_name,display_name,icon,route,component_key,resource_type,sort_order,status)
VALUES ('workshop','Workshop','users','/dashboard/workshop','workshop','PAGE',64,'Active')
ON DUPLICATE KEY UPDATE display_name=VALUES(display_name),route=VALUES(route),component_key=VALUES(component_key),resource_type='PAGE';
-- Only insert missing grants: rerunning preserves later administrator customizations.
INSERT IGNORE INTO permissions(position_id,resource_id,can_view,can_create,can_edit,can_delete,can_export,can_upload,can_download,data_scope)
SELECT p.id,r.id,1,1,1,1,1,1,1,'ALL' FROM positions p JOIN resources r ON r.resource_name='workshop'
WHERE p.id IN(1,2) OR LOWER(p.position_name) REGEXP '(^|[^a-z])(hr|human resources|super admin|administrator|admin)([^a-z]|$)';
INSERT IGNORE INTO permissions(position_id,resource_id,can_view,can_create,can_edit,can_delete,can_export,can_upload,can_download,data_scope)
SELECT p.id,r.id,1,1,1,0,1,1,1,'OWN' FROM positions p JOIN resources r ON r.resource_name='workshop' WHERE p.status='Active';
