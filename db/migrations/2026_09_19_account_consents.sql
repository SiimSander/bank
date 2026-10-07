CREATE TABLE account_consents (
	id INT PRIMARY KEY AUTO_INCREMENT,
	account_id INT NOT NULL,
	consent_type ENUM('terms', 'privacy', 'bank_ais') NOT NULL,
	version VARCHAR(20) NOT NULL,
	accepted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	ip_address VARCHAR(45) NULL,
	user_agent VARCHAR(255) NULL,
	FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
	INDEX idx_account_consents_account_type (account_id, consent_type, accepted_at)
);
