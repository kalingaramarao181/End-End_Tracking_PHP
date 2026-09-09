-- Preserve every manual status transition without changing application.date_created.
ALTER TABLE application_process_history
    MODIFY event_type ENUM('submitted','interview','placed') NOT NULL,
    ADD COLUMN previous_process_id TINYINT UNSIGNED NULL AFTER application_id,
    ADD COLUMN new_process_id TINYINT UNSIGNED NULL AFTER previous_process_id;

UPDATE application_process_history
SET new_process_id = CASE event_type
    WHEN 'submitted' THEN 1
    WHEN 'interview' THEN 2
    WHEN 'placed' THEN 3
END
WHERE new_process_id IS NULL;

-- The old unique key caused a later placement to overwrite the original event.
ALTER TABLE application_process_history
    DROP INDEX uq_application_event_round,
    ADD KEY idx_application_event_date (event_type, created_at, application_id);