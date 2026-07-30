ALTER TABLE leave_requests
  ADD COLUMN approval_token_hash CHAR(64) NULL AFTER manager_comment,
  ADD COLUMN approval_token_expires_at DATETIME NULL AFTER approval_token_hash,
  ADD COLUMN reviewer_name VARCHAR(255) NULL AFTER reviewed_by,
  ADD COLUMN review_source VARCHAR(20) NULL AFTER reviewer_name,
  ADD UNIQUE KEY uq_leave_approval_token_hash (approval_token_hash);