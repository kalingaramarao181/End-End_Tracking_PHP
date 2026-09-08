-- One encrypted, organization-wide SMTP mailbox for all document reminders.
CREATE TABLE IF NOT EXISTS document_reminder_mail_settings (
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
  CONSTRAINT chk_document_reminder_singleton CHECK (id = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;