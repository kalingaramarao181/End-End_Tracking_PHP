-- Record when an application enters or is updated in an interview/placement stage.
ALTER TABLE application
    ADD COLUMN interview_updated_at DATETIME NULL AFTER interview_slot,
    ADD COLUMN placement_updated_at DATETIME NULL AFTER interview_updated_at;

-- The legacy `date` column was updated whenever process status changed.
UPDATE application
SET interview_updated_at = COALESCE(interview_updated_at, TIMESTAMP(`date`))
WHERE process_id = 2;

UPDATE application
SET placement_updated_at = COALESCE(placement_updated_at, TIMESTAMP(`date`))
WHERE process_id = 3;

CREATE INDEX idx_application_interview_updated ON application(process_id, interview_updated_at);
CREATE INDEX idx_application_placement_updated ON application(process_id, placement_updated_at);