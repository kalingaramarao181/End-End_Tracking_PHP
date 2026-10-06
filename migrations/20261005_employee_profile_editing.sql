-- Separate BDT counter; runtime allocator checks the highest existing BDT-I suffix.
-- Existing employee codes remain unchanged.
INSERT IGNORE INTO employee_id_sequence(id,`last_value`) VALUES(2,0);
-- The public edit deadline is completed_at + 4 days; no new token columns are needed.
