-- Shared allocator serializes manual and public employee creation.
CREATE TABLE IF NOT EXISTS employee_id_sequence (
 id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
 `last_value` BIGINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB;
INSERT IGNORE INTO employee_id_sequence(id,`last_value`) VALUES(1,0);

-- Organization mailbox for automatic leave approval requests.
-- Encrypted organization-wide SMTP mailbox for automatic leave approval emails.
CREATE TABLE IF NOT EXISTS attendance_leave_mail_settings (
  id TINYINT UNSIGNED NOT NULL DEFAULT 1,
  smtp_host VARCHAR(190) NOT NULL,
  smtp_port SMALLINT UNSIGNED NOT NULL DEFAULT 587,
  encryption VARCHAR(20) NOT NULL DEFAULT 'tls',
  from_email VARCHAR(190) NOT NULL,
  encrypted_password TEXT NOT NULL,
  from_name VARCHAR(190) NOT NULL DEFAULT 'BeeData Technologies',
  to_email VARCHAR(190) NOT NULL,
  verified_at DATETIME NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT chk_attendance_leave_singleton CHECK (id = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;