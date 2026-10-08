-- Restore the previously supplied employee name for BDT-I-081 only.
-- This is safe to rerun and does not change other employees or existing correct names.
UPDATE employees
SET firstname = 'Ramarao', lastname = 'Kalinga'
WHERE employee_id = 'BDT-I-081'
  AND firstname = 'BeeData'
  AND lastname = 'Technologies';

SELECT employee_id, TRIM(CONCAT_WS(' ', firstname, lastname)) AS legal_name
FROM employees WHERE employee_id = 'BDT-I-081';
