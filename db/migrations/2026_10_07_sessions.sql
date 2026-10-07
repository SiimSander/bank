CREATE TABLE IF NOT EXISTS sessions (
	id VARCHAR(128) PRIMARY KEY,
	data MEDIUMBLOB NOT NULL,
	last_activity INT UNSIGNED NOT NULL,
	INDEX idx_sessions_last_activity (last_activity)
);
