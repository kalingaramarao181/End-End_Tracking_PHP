-- Allows administrators to distinguish calendar-created attendance from device records.
ALTER TABLE attendance
    ADD COLUMN admin_created TINYINT(1) NOT NULL DEFAULT 0 AFTER status;

CREATE INDEX idx_attendance_admin_created
    ON attendance (employee_id, date, admin_created);
