-- Enterprise payroll and payslip support.
SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='employees' AND COLUMN_NAME='payroll_email')=0,'ALTER TABLE employees ADD COLUMN payroll_email VARCHAR(190) NULL AFTER contact_info','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='employees' AND COLUMN_NAME='monthly_salary')=0,'ALTER TABLE employees ADD COLUMN monthly_salary DECIMAL(12,2) NULL AFTER payroll_email','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
CREATE TABLE IF NOT EXISTS employee_payslips (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, employee_id INT NOT NULL, pay_month CHAR(7) NOT NULL,
 payslip_number VARCHAR(50) NOT NULL, currency CHAR(3) NOT NULL DEFAULT 'INR', payroll_snapshot JSON NOT NULL,
 gross_earnings DECIMAL(12,2) NOT NULL DEFAULT 0, total_deductions DECIMAL(12,2) NOT NULL DEFAULT 0,
 net_pay DECIMAL(12,2) NOT NULL DEFAULT 0, status ENUM('generated','sent') NOT NULL DEFAULT 'generated',
 generated_by INT UNSIGNED NULL, generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, sent_at DATETIME NULL,
 UNIQUE KEY uq_employee_payslip_month(employee_id,pay_month), UNIQUE KEY uq_payslip_number(payslip_number),
 KEY idx_payslip_status_month(status,pay_month),
 CONSTRAINT fk_payslip_employee FOREIGN KEY(employee_id) REFERENCES employees(id) ON DELETE CASCADE,
 CONSTRAINT fk_payslip_generated_by FOREIGN KEY(generated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
