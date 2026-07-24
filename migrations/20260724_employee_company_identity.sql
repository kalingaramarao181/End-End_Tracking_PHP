-- Employee legal identity -> application user/company identity.
-- Nullable allows employees to exist before an administrator assigns a user.
ALTER TABLE employees
    ADD PRIMARY KEY (id),
    ADD COLUMN user_id INT(10) UNSIGNED NULL AFTER id,
    ADD UNIQUE KEY uq_employees_user_id (user_id),
    ADD UNIQUE KEY uq_employees_employee_id (employee_id),
    ADD INDEX idx_employees_legal_name (firstname, lastname),
    ADD CONSTRAINT fk_employees_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL;
