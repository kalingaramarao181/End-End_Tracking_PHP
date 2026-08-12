-- Add privileges field to document_reminders table to track dollar value
ALTER TABLE document_reminders ADD COLUMN privileges DECIMAL(10, 2) NULL DEFAULT NULL AFTER status;
ALTER TABLE candidate_documents ADD COLUMN privileges DECIMAL(10, 2) NULL DEFAULT NULL AFTER document_details;

-- Create index for privileges search
CREATE INDEX idx_privileges ON document_reminders (privileges);
