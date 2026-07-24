-- Run after importing the legacy attendance.sql dataset.
CREATE INDEX idx_attendance_employee_date
    ON attendance (employee_id, date);
