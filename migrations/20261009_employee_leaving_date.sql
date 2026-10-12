-- Apply once before enabling departure-date editing. Existing dates stay unknown.
ALTER TABLE employees ADD COLUMN date_of_leaving DATE NULL AFTER date_of_joining;
