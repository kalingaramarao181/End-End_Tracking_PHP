-- Makes all user-entered W-2 consultant form fields optional.
-- Safe to run after 20260826_w2_forms.sql.
ALTER TABLE w2_forms
    MODIFY consultant_name VARCHAR(150) NULL,
    MODIFY consultant_email VARCHAR(180) NULL;
