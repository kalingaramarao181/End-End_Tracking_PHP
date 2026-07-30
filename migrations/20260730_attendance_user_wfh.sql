-- Per-employee WFH exception while company attendance IP restriction remains enabled.
CREATE TABLE attendance_wfh_permissions (
    employee_id INT NOT NULL PRIMARY KEY,
    wfh_allowed TINYINT(1) NOT NULL DEFAULT 0,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_attendance_wfh_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    CONSTRAINT fk_attendance_wfh_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Global bypass is no longer used; restriction stays enabled and exceptions are user-specific.
UPDATE attendance_settings SET ip_restriction_enabled=1 WHERE id=1;