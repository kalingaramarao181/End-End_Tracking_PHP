-- Recruiting Resume Match: per-user comparison metadata. No resume files or credentials.
CREATE TABLE IF NOT EXISTS resume_match_history (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 job_description TEXT NOT NULL,
 state_filter VARCHAR(3) NOT NULL DEFAULT 'ALL',
 source VARCHAR(16) NOT NULL,
 candidate_count SMALLINT UNSIGNED NOT NULL,
 top_score SMALLINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL,
 INDEX idx_resume_match_owner (user_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
