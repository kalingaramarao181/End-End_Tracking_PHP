ALTER TABLE employees MODIFY COLUMN position_id INT NULL, MODIFY COLUMN schedule_id INT NULL;
CREATE TABLE IF NOT EXISTS employee_onboarding_invites (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, personal_email VARCHAR(190) NOT NULL, token_hash CHAR(64) NOT NULL UNIQUE,
 created_by INT UNSIGNED NULL, expires_at DATETIME NOT NULL, completed_at DATETIME NULL, employee_id INT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_onboarding_expiry(expires_at,completed_at),
 CONSTRAINT fk_onboarding_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_onboarding_employee FOREIGN KEY(employee_id) REFERENCES employees(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;