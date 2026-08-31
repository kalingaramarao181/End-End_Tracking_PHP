CREATE TABLE IF NOT EXISTS application_process_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    event_type ENUM('interview','placed') NOT NULL,
    round_number INT UNSIGNED NULL,
    interview_slot VARCHAR(120) NULL,
    feedback TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_application_timeline (application_id, created_at, id),
    UNIQUE KEY uq_application_event_round (application_id, event_type, round_number),
    CONSTRAINT fk_process_history_application FOREIGN KEY (application_id) REFERENCES application(id) ON DELETE CASCADE,
    CONSTRAINT fk_process_history_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO application_process_history(application_id,event_type,round_number,interview_slot,feedback,created_at)
SELECT id,'interview',1,interview_slot,feedback,COALESCE(interview_updated_at,`date`,date_created,NOW())
FROM application
WHERE interview_slot IS NOT NULL AND TRIM(interview_slot)<>'';

INSERT IGNORE INTO application_process_history(application_id,event_type,round_number,feedback,created_at)
SELECT id,'placed',0,NULL,COALESCE(placement_updated_at,`date`,date_created,NOW())
FROM application
WHERE process_id=3;