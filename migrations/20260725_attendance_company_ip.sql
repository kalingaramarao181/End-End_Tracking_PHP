-- Restrict employee self-service Time In / Time Out to the company network.
-- Run this after 20260724_enterprise_attendance.sql.

ALTER TABLE attendance_settings
    ADD COLUMN allowed_ip_addresses TEXT NULL AFTER grace_minutes;

ALTER TABLE attendance
    ADD COLUMN clock_in_ip VARCHAR(45) NULL AFTER clock_in_utc,
    ADD COLUMN clock_out_ip VARCHAR(45) NULL AFTER clock_out_utc;

-- IMPORTANT: replace these examples with the company's real public IP address.
-- Multiple exact IPs or CIDR networks can be comma-separated.
-- Examples: 203.0.113.25
--           203.0.113.25,198.51.100.0/24
UPDATE attendance_settings
SET allowed_ip_addresses = ''
WHERE id = 1;

UPDATE resources
SET route = '/dashboard/attendance',
    component_key = 'attendance',
    display_name = 'Attendance Management'
WHERE resource_name = 'attendance';
