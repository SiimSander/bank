ALTER TABLE accounts
	ADD COLUMN email_verified_at TIMESTAMP NULL AFTER password;

CREATE TABLE IF NOT EXISTS password_reset_tokens (
	id INT PRIMARY KEY AUTO_INCREMENT,
	account_id INT NOT NULL,
	token_hash CHAR(64) NOT NULL,
	expires_at TIMESTAMP NOT NULL,
	used_at TIMESTAMP NULL,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
	INDEX idx_password_reset_token_hash (token_hash)
);

CREATE TABLE IF NOT EXISTS email_verification_tokens (
	id INT PRIMARY KEY AUTO_INCREMENT,
	account_id INT NOT NULL,
	token_hash CHAR(64) NOT NULL,
	expires_at TIMESTAMP NOT NULL,
	used_at TIMESTAMP NULL,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
	INDEX idx_email_verification_token_hash (token_hash)
);
