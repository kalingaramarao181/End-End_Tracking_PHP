-- Allow Super Admin to temporarily disable attendance IP enforcement for WFH.
ALTER TABLE attendance_settings
    ADD COLUMN ip_restriction_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER allowed_ip_addresses;

UPDATE attendance_settings SET ip_restriction_enabled = 1 WHERE id = 1;