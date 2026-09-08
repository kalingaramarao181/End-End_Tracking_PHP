SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='employees' AND COLUMN_NAME='date_of_joining')=0,'ALTER TABLE employees ADD COLUMN date_of_joining DATE NULL AFTER birthdate','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
CREATE TABLE IF NOT EXISTS attendance_monthly_adjustments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, employee_id INT NOT NULL, attendance_month CHAR(7) NOT NULL,
 overrides_json JSON NOT NULL, updated_by INT UNSIGNED NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_employee_attendance_month(employee_id,attendance_month),
 CONSTRAINT fk_attendance_adjustment_employee FOREIGN KEY(employee_id) REFERENCES employees(id) ON DELETE CASCADE,
 CONSTRAINT fk_attendance_adjustment_user FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;