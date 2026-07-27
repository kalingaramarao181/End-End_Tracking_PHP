-- Enterprise attendance: run once against the e2e_tracking database.
-- All business dates/times are America/New_York (EST/EDT); UTC timestamps are
-- retained for auditability.

ALTER TABLE attendance
    ADD COLUMN work_status ENUM('working','completed','half_day','absent','leave','holiday')
        NOT NULL DEFAULT 'completed' AFTER admin_created,
    ADD COLUMN source ENUM('self_service','admin','import','device')
        NOT NULL DEFAULT 'import' AFTER work_status,
    ADD COLUMN notes VARCHAR(500) NULL AFTER num_hr,
    ADD COLUMN clock_in_utc DATETIME NULL AFTER notes,
    ADD COLUMN clock_out_utc DATETIME NULL AFTER clock_in_utc,
    ADD COLUMN updated_by INT UNSIGNED NULL AFTER clock_out_utc,
    ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP AFTER updated_by;

-- Legacy imports contain multiple punches on some dates, so this deliberately
-- remains a non-unique lookup index. Self-service writes are serialized by
-- locking the employee row.
CREATE INDEX idx_attendance_workday_status
    ON attendance(employee_id, date, work_status);

CREATE TABLE attendance_settings (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    timezone VARCHAR(64) NOT NULL DEFAULT 'America/New_York',
    work_start TIME NOT NULL DEFAULT '09:30:00',
    work_end TIME NOT NULL DEFAULT '18:30:00',
    full_day_hours DECIMAL(4,2) NOT NULL DEFAULT 9.00,
    half_day_below_hours DECIMAL(4,2) NOT NULL DEFAULT 5.00,
    grace_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    updated_by INT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO attendance_settings
    (id, timezone, work_start, work_end, full_day_hours, half_day_below_hours, grace_minutes)
VALUES (1, 'America/New_York', '09:30:00', '18:30:00', 9.00, 5.00, 0);

UPDATE attendance
SET source = CASE WHEN admin_created = 1 THEN 'admin' ELSE 'import' END,
    work_status = CASE
        WHEN time_out = '00:00:00' THEN 'working'
        WHEN num_hr < 5 THEN 'half_day'
        ELSE 'completed'
    END;

CREATE TABLE holidays (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    holiday_date DATE NOT NULL,
    name VARCHAR(150) NOT NULL,
    description VARCHAR(500) NULL,
    is_optional TINYINT(1) NOT NULL DEFAULT 0,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_holiday_date (holiday_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE leave_requests (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    leave_type ENUM('paid','sick','casual','unpaid','bereavement','other')
        NOT NULL DEFAULT 'paid',
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    duration ENUM('full_day','first_half','second_half') NOT NULL DEFAULT 'full_day',
    reason VARCHAR(1000) NOT NULL,
    status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
    manager_comment VARCHAR(1000) NULL,
    reviewed_by INT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_leave_employee_dates (employee_id, start_date, end_date),
    KEY idx_leave_status_dates (status, start_date, end_date),
    CONSTRAINT fk_leave_employee FOREIGN KEY (employee_id)
        REFERENCES employees(id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Move Attendance from the personal profile to its own HR/Admin workspace.
UPDATE resources
SET route = '/dashboard/attendance',
    component_key = 'attendance',
    display_name = 'Attendance Management'
WHERE resource_name = 'attendance';
