-- TeamFlow integration: additive and repeatable. Never import the source dump directly into E2E.

-- Apply this schema, then run the CLI importer against its prepared private manifest.

CREATE TABLE IF NOT EXISTS teamflow_candidates (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 user_id INT UNSIGNED NOT NULL UNIQUE,
 source_key VARCHAR(60) NOT NULL DEFAULT 'teamflow', source_user_id INT NULL,
 firstname VARCHAR(100) NOT NULL DEFAULT '', lastname VARCHAR(100) NOT NULL DEFAULT '',
 contact_info VARCHAR(80) NOT NULL DEFAULT '', birthdate DATE NULL, current_address TEXT NULL, permanent_address TEXT NULL,
 profile_json LONGTEXT NULL, collection_json LONGTEXT NULL, photo_json LONGTEXT NULL,
 created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
 UNIQUE KEY uq_teamflow_source_user(source_key,source_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS teamflow_imports (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, source_hash CHAR(64) NOT NULL UNIQUE,
 source_key VARCHAR(60) NOT NULL, summary_json LONGTEXT NOT NULL, imported_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS teamflow_files (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, entity_type VARCHAR(40) NOT NULL, entity_id INT NOT NULL,
 original_path VARCHAR(1000) NOT NULL, storage_name VARCHAR(100) NULL, original_name VARCHAR(255) NOT NULL,
 mime_type VARCHAR(100) NULL, file_size BIGINT UNSIGNED NULL, missing TINYINT(1) NOT NULL DEFAULT 0,
 uploaded_by INT UNSIGNED NULL, created_at DATETIME NOT NULL,
 INDEX idx_tf_file_entity(entity_type,entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `teamflow_chat_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `project_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  INDEX `idx_tf_project_id` (`project_id`),
  INDEX `idx_tf_sender_id` (`sender_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `teamflow_employee_status_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `work_status_id` int(11) NOT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  INDEX `idx_tf_work_status_id` (`work_status_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `teamflow_employee_work_status` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `login_time` datetime DEFAULT current_timestamp(),
  `logout_time` datetime DEFAULT NULL,
  `module_name` varchar(100) DEFAULT NULL,
  `working_on` text DEFAULT NULL,
  `worked_on` text DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `login_status` tinyint(1) DEFAULT 0,
  candidate_id BIGINT UNSIGNED NULL,
  deleted_at DATETIME NULL,
  INDEX `idx_tf_user_id` (`user_id`),
  INDEX `idx_tf_project_id` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `teamflow_work_holidays` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `title` varchar(255) NOT NULL,
  `date` date NOT NULL,
  `type` varchar(50) DEFAULT 'Public',
  `color` varchar(20) DEFAULT '#ff6b00',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `teamflow_issues` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `project` varchar(255) DEFAULT NULL,
  `issue_type` varchar(255) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `summary` text NOT NULL,
  `description` text DEFAULT NULL,
  `priority` varchar(50) DEFAULT NULL,
  `team` varchar(255) DEFAULT NULL,
  `labels` varchar(255) DEFAULT NULL,
  `sprint` varchar(255) DEFAULT NULL,
  `linked_issue_type` varchar(255) DEFAULT NULL,
  `linked_issue` varchar(255) DEFAULT NULL,
  `assignee` varchar(255) DEFAULT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  assignee_user_id INT UNSIGNED NULL,
  created_by INT UNSIGNED NULL,
  deleted_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `teamflow_legacy_positions` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `teamflow_legacy_position_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `position_id` int(11) NOT NULL,
  `resource_id` int(11) NOT NULL,
  `can_view` tinyint(1) DEFAULT 0,
  `can_create` tinyint(1) DEFAULT 0,
  `can_edit` tinyint(1) DEFAULT 0,
  `can_delete` tinyint(1) DEFAULT 0,
  `can_assign` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `teamflow_projects` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` varchar(255) NOT NULL,
  `projectKey` varchar(255) NOT NULL,
  `type` varchar(100) DEFAULT NULL,
  `lead` varchar(255) DEFAULT NULL,
  `projectURL` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `projectLogo` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  lead_user_id INT UNSIGNED NULL,
  created_by INT UNSIGNED NULL,
  deleted_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `teamflow_project_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `project_id` int(11) NOT NULL,
  `document_path` varchar(500) NOT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `uploaded_at` datetime DEFAULT current_timestamp(),
  INDEX `idx_tf_project_id` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `teamflow_project_github_configs` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `project_id` int(11) NOT NULL,
  `repo_url` varchar(500) NOT NULL,
  `repo_name` varchar(255) DEFAULT NULL,
  `github_token` text DEFAULT NULL,
  `last_synced_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  INDEX `idx_tf_project_id` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `teamflow_project_task_breakdowns` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `project_id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `subtask_name` varchar(255) NOT NULL,
  `assignee_id` int(11) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'pending',
  `percentage` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  INDEX `idx_tf_project_id` (`project_id`),
  INDEX `idx_tf_assignee_id` (`assignee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `teamflow_project_team_members` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `team_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` varchar(50) DEFAULT 'member',
  `is_lead` tinyint(1) DEFAULT 0,
  `joined_at` timestamp NULL DEFAULT current_timestamp(),
  INDEX `idx_tf_user_id` (`user_id`),
  INDEX `idx_tf_team_id` (`team_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `teamflow_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `userId` int(11) NOT NULL,
  `reportText` text NOT NULL,
  `reportImage` varchar(255) NOT NULL,
  `createdAt` timestamp NULL DEFAULT current_timestamp(),
  deleted_at DATETIME NULL,
  INDEX `idx_tf_userId` (`userId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `teamflow_legacy_resources` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` varchar(100) NOT NULL,
  `resource_key` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `teamflow_teams` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `team_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `project_id` int(11) NOT NULL,
  `team_members` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `created_by` int(11) NOT NULL,
  `updated_by` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  INDEX `idx_tf_project_id` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `teamflow_legacy_user_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id` int(11) NOT NULL,
  `resource_id` int(11) NOT NULL,
  `can_view` tinyint(1) DEFAULT NULL,
  `can_create` tinyint(1) DEFAULT NULL,
  `can_edit` tinyint(1) DEFAULT NULL,
  `can_delete` tinyint(1) DEFAULT NULL,
  `can_assign` tinyint(1) DEFAULT NULL,
  `assigned_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  INDEX `idx_tf_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `teamflow_legacy_user_positions` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id` int(11) NOT NULL,
  `position_id` int(11) NOT NULL,
  `assigned_by` int(11) DEFAULT NULL,
  `assigned_at` timestamp NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1,
  INDEX `idx_tf_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `teamflow_user_project_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `assigned_by` int(11) DEFAULT NULL,
  `assigned_at` timestamp NULL DEFAULT current_timestamp(),
  INDEX `idx_tf_user_id` (`user_id`),
  INDEX `idx_tf_project_id` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `teamflow_weekly_timesheets` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id` int(11) NOT NULL,
  `week_no` int(11) NOT NULL,
  `year` int(11) NOT NULL,
  `entry_type` enum('project','leave','training') NOT NULL DEFAULT 'project',
  `project_id` int(11) DEFAULT NULL,
  `task_name` varchar(255) NOT NULL,
  `worked_on` text DEFAULT NULL,
  `mon_hours` decimal(5,2) DEFAULT 0,
  `tue_hours` decimal(5,2) DEFAULT 0,
  `wed_hours` decimal(5,2) DEFAULT 0,
  `thu_hours` decimal(5,2) DEFAULT 0,
  `fri_hours` decimal(5,2) DEFAULT 0,
  `sat_hours` decimal(5,2) DEFAULT 0,
  `sun_hours` decimal(5,2) DEFAULT 0,
  `total_hours` decimal(7,2) DEFAULT 0,
  `status` enum('Draft','Pending','Approved','Rejected') NOT NULL DEFAULT 'Draft',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  deleted_at DATETIME NULL,
  INDEX `idx_tf_user_id` (`user_id`),
  INDEX `idx_tf_project_id` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO positions(position_name,description,status) VALUES('Candidate','TeamFlow candidate account','Active');

INSERT INTO resources(resource_name,display_name,icon,route,component_key,resource_type,sort_order,status) VALUES ('teamflow_candidates','TeamFlow Candidates','users','/dashboard/teamflow/candidates','teamflow-candidates','PAGE',71,'Active') ON DUPLICATE KEY UPDATE display_name=VALUES(display_name),route=VALUES(route),component_key=VALUES(component_key);

INSERT IGNORE INTO permissions(position_id,resource_id,can_view,can_create,can_edit,can_delete,can_export,can_upload,can_download,can_approve,can_reject,can_assign,can_manage,data_scope) SELECT p.id,r.id,1,1,1,1,1,1,1,1,1,1,1,'ALL' FROM positions p JOIN resources r ON r.resource_name='teamflow_candidates' WHERE p.id IN(1,2);

INSERT INTO resources(resource_name,display_name,icon,route,component_key,resource_type,sort_order,status) VALUES ('teamflow_projects','Projects','briefcase','/dashboard/teamflow/projects','teamflow-projects','PAGE',72,'Active') ON DUPLICATE KEY UPDATE display_name=VALUES(display_name),route=VALUES(route),component_key=VALUES(component_key);

INSERT IGNORE INTO permissions(position_id,resource_id,can_view,can_create,can_edit,can_delete,can_export,can_upload,can_download,can_approve,can_reject,can_assign,can_manage,data_scope) SELECT p.id,r.id,1,1,1,1,1,1,1,1,1,1,1,'ALL' FROM positions p JOIN resources r ON r.resource_name='teamflow_projects' WHERE p.id IN(1,2);

INSERT IGNORE INTO permissions(position_id,resource_id,can_view,can_create,can_edit,can_delete,can_export,can_upload,can_download,data_scope) SELECT p.id,r.id,1,0,0,0,1,0,1,'OWN' FROM positions p JOIN resources r ON r.resource_name='teamflow_projects' WHERE LOWER(p.position_name)='candidate';

INSERT INTO resources(resource_name,display_name,icon,route,component_key,resource_type,sort_order,status) VALUES ('teamflow_issues','Issues','clipboard','/dashboard/teamflow/issues','teamflow-issues','PAGE',73,'Active') ON DUPLICATE KEY UPDATE display_name=VALUES(display_name),route=VALUES(route),component_key=VALUES(component_key);

INSERT IGNORE INTO permissions(position_id,resource_id,can_view,can_create,can_edit,can_delete,can_export,can_upload,can_download,can_approve,can_reject,can_assign,can_manage,data_scope) SELECT p.id,r.id,1,1,1,1,1,1,1,1,1,1,1,'ALL' FROM positions p JOIN resources r ON r.resource_name='teamflow_issues' WHERE p.id IN(1,2);

INSERT IGNORE INTO permissions(position_id,resource_id,can_view,can_create,can_edit,can_delete,can_export,can_upload,can_download,data_scope) SELECT p.id,r.id,1,1,1,0,1,1,1,'OWN' FROM positions p JOIN resources r ON r.resource_name='teamflow_issues' WHERE LOWER(p.position_name)='candidate';

INSERT INTO resources(resource_name,display_name,icon,route,component_key,resource_type,sort_order,status) VALUES ('teamflow_timesheets','Timesheets','calendar','/dashboard/teamflow/timesheets','teamflow-timesheets','PAGE',74,'Active') ON DUPLICATE KEY UPDATE display_name=VALUES(display_name),route=VALUES(route),component_key=VALUES(component_key);

INSERT IGNORE INTO permissions(position_id,resource_id,can_view,can_create,can_edit,can_delete,can_export,can_upload,can_download,can_approve,can_reject,can_assign,can_manage,data_scope) SELECT p.id,r.id,1,1,1,1,1,1,1,1,1,1,1,'ALL' FROM positions p JOIN resources r ON r.resource_name='teamflow_timesheets' WHERE p.id IN(1,2);

INSERT IGNORE INTO permissions(position_id,resource_id,can_view,can_create,can_edit,can_delete,can_export,can_upload,can_download,data_scope) SELECT p.id,r.id,1,1,1,1,1,1,1,'OWN' FROM positions p JOIN resources r ON r.resource_name='teamflow_timesheets' WHERE LOWER(p.position_name)='candidate';

INSERT INTO resources(resource_name,display_name,icon,route,component_key,resource_type,sort_order,status) VALUES ('teamflow_reports','Reports','file','/dashboard/teamflow/reports','teamflow-reports','PAGE',75,'Active') ON DUPLICATE KEY UPDATE display_name=VALUES(display_name),route=VALUES(route),component_key=VALUES(component_key);

INSERT IGNORE INTO permissions(position_id,resource_id,can_view,can_create,can_edit,can_delete,can_export,can_upload,can_download,can_approve,can_reject,can_assign,can_manage,data_scope) SELECT p.id,r.id,1,1,1,1,1,1,1,1,1,1,1,'ALL' FROM positions p JOIN resources r ON r.resource_name='teamflow_reports' WHERE p.id IN(1,2);

INSERT IGNORE INTO permissions(position_id,resource_id,can_view,can_create,can_edit,can_delete,can_export,can_upload,can_download,data_scope) SELECT p.id,r.id,1,1,1,1,1,1,1,'OWN' FROM positions p JOIN resources r ON r.resource_name='teamflow_reports' WHERE LOWER(p.position_name)='candidate';

INSERT INTO resources(resource_name,display_name,icon,route,component_key,resource_type,sort_order,status) VALUES ('teamflow_attendance','Candidate Attendance','calendar','/dashboard/teamflow/attendance','teamflow-attendance','PAGE',76,'Active') ON DUPLICATE KEY UPDATE display_name=VALUES(display_name),route=VALUES(route),component_key=VALUES(component_key);

INSERT IGNORE INTO permissions(position_id,resource_id,can_view,can_create,can_edit,can_delete,can_export,can_upload,can_download,can_approve,can_reject,can_assign,can_manage,data_scope) SELECT p.id,r.id,1,1,1,1,1,1,1,1,1,1,1,'ALL' FROM positions p JOIN resources r ON r.resource_name='teamflow_attendance' WHERE p.id IN(1,2);

INSERT IGNORE INTO permissions(position_id,resource_id,can_view,can_create,can_edit,can_delete,can_export,can_upload,can_download,data_scope) SELECT p.id,r.id,1,1,1,0,1,1,1,'OWN' FROM positions p JOIN resources r ON r.resource_name='teamflow_attendance' WHERE LOWER(p.position_name)='candidate';

INSERT IGNORE INTO permissions(position_id,resource_id,can_view,can_edit,can_upload,can_download,data_scope) SELECT p.id,r.id,1,1,1,1,'OWN' FROM positions p JOIN resources r ON r.resource_name='profile' WHERE LOWER(p.position_name)='candidate';
