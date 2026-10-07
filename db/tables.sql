CREATE TABLE users(
	id INT PRIMARY KEY AUTO_INCREMENT,
	name VARCHAR(100) NOT NULL,
	email VARCHAR(100) NOT NULL
);

CREATE TABLE accounts (
	id INT PRIMARY KEY AUTO_INCREMENT,
	name VARCHAR(100) NOT NULL,
	username VARCHAR(50) NOT NULL,
	email VARCHAR(255) NULL,
	password VARCHAR(255) NOT NULL,
	email_verified_at TIMESTAMP NULL,
	guaranteed_monthly_income FLOAT NULL,
	opening_balances_set_at TIMESTAMP NULL,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE password_reset_tokens (
	id INT PRIMARY KEY AUTO_INCREMENT,
	account_id INT NOT NULL,
	token_hash CHAR(64) NOT NULL,
	expires_at TIMESTAMP NOT NULL,
	used_at TIMESTAMP NULL,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
	INDEX idx_password_reset_token_hash (token_hash)
);

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

CREATE TABLE email_verification_tokens (
	id INT PRIMARY KEY AUTO_INCREMENT,
	account_id INT NOT NULL,
	token_hash CHAR(64) NOT NULL,
	expires_at TIMESTAMP NOT NULL,
	used_at TIMESTAMP NULL,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
	INDEX idx_email_verification_token_hash (token_hash)
);

CREATE TABLE win_cards (
	id INT PRIMARY KEY AUTO_INCREMENT,
	account_id INT NOT NULL,
	card_date DATE NOT NULL,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	UNIQUE (account_id, card_date),
	FOREIGN KEY (account_id) REFERENCES accounts(id)
);

CREATE TABLE win_card_items(
	id INT PRIMARY KEY AUTO_INCREMENT,
	card_id INT NOT NULL,
	title VARCHAR(150) NOT NULL,
	status ENUM('pending', 'done', 'failed') NOT NULL DEFAULT('pending'),
	position TINYINT UNSIGNED NOT NULL DEFAULT 0,
	updated_at TIMESTAMP NULL,
	FOREIGN KEY (card_id) REFERENCES win_cards(id)
);

CREATE TABLE bank_entry_types (
	id INT PRIMARY KEY AUTO_INCREMENT,
	account_id INT NOT NULL,
	slug VARCHAR(64) NOT NULL,
	label VARCHAR(100) NOT NULL,
	color_hex CHAR(7) NOT NULL,
	income_percent DECIMAL(5, 4) NULL,
	goal_income_from DATE NULL,
	balance_mode ENUM('wallet_in', 'wallet_out', 'pot', 'adjustment') NOT NULL,
	is_active TINYINT(1) NOT NULL DEFAULT 1,
	is_system TINYINT(1) NOT NULL DEFAULT 0,
	show_in_pills TINYINT(1) NOT NULL DEFAULT 1,
	sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	UNIQUE (account_id, slug),
	FOREIGN KEY (account_id) REFERENCES accounts(id)
);

CREATE TABLE bank_entry_type_income_percent_history (
	id INT PRIMARY KEY AUTO_INCREMENT,
	bank_entry_type_id INT NOT NULL,
	effective_from DATE NOT NULL,
	income_percent DECIMAL(5, 4) NOT NULL,
	UNIQUE (bank_entry_type_id, effective_from),
	FOREIGN KEY (bank_entry_type_id) REFERENCES bank_entry_types(id) ON DELETE CASCADE
);

CREATE TABLE bank_entries (
	id INT PRIMARY KEY AUTO_INCREMENT,
	account_id INT NOT NULL,
	entry_date DATE NOT NULL,
	type VARCHAR(64) NOT NULL,
	method ENUM('card', 'cash') NOT NULL DEFAULT 'card',
	amount FLOAT NOT NULL,
	note VARCHAR(150) NULL,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	entry_reference VARCHAR(64) NULL,
	is_pending TINYINT(1) NOT NULL DEFAULT 0,
	UNIQUE (account_id, entry_reference),
	FOREIGN KEY (account_id) REFERENCES accounts(id)
);

CREATE TABLE monthly_stat_by_type (
	account_id INT NOT NULL,
	stat_month DATE NOT NULL,
	type_slug VARCHAR(64) NOT NULL,
	goal_amount FLOAT NOT NULL DEFAULT 0,
	actual_amount FLOAT NOT NULL DEFAULT 0,
	PRIMARY KEY (account_id, stat_month, type_slug),
	FOREIGN KEY (account_id) REFERENCES accounts(id)
);

CREATE TABLE goal_miss_carryover (
	account_id INT NOT NULL,
	stat_month DATE NOT NULL,
	type_slug VARCHAR(64) NOT NULL,
	missed_total FLOAT NOT NULL DEFAULT 0,
	applied_total FLOAT NOT NULL DEFAULT 0,
	PRIMARY KEY (account_id, stat_month, type_slug),
	FOREIGN KEY (account_id) REFERENCES accounts(id)
);

CREATE TABLE goal_miss_processed_days (
	account_id INT NOT NULL,
	entry_date DATE NOT NULL,
	PRIMARY KEY (account_id, entry_date),
	FOREIGN KEY (account_id) REFERENCES accounts(id)
);

CREATE TABLE bank_connections (
	id INT PRIMARY KEY AUTO_INCREMENT,
	account_id INT NOT NULL,
	aspsp_name VARCHAR(100) NOT NULL,
	aspsp_country VARCHAR(2) NOT NULL,
	session_id VARCHAR(64) NOT NULL,
	bank_account_uid VARCHAR(64) NOT NULL,
	iban VARCHAR(34) NOT NULL,
	valid_until TIMESTAMP NOT NULL,
	is_main_account TINYINT(1) NOT NULL DEFAULT 0,
	last_synced_at TIMESTAMP NULL DEFAULT NULL,
	last_sync_attempt_at TIMESTAMP NULL DEFAULT NULL,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	UNIQUE (account_id, bank_account_uid),
	FOREIGN KEY (account_id) REFERENCES accounts(id)
);

CREATE TABLE monthly_stats (
	id INT PRIMARY KEY AUTO_INCREMENT,
	account_id INT NOT NULL,
	stat_month DATE NOT NULL,
	income FLOAT NOT NULL DEFAULT 0,
	essential_expenses_goal FLOAT NOT NULL DEFAULT 0,
	expenses_actual FLOAT NOT NULL DEFAULT 0,
	saving_goal FLOAT NOT NULL DEFAULT 0,
	saving_actual FLOAT NOT NULL DEFAULT 0,
	investment_goal FLOAT NOT NULL DEFAULT 0,
	investment_actual FLOAT NOT NULL DEFAULT 0,
	kogumiskonto_actual FLOAT NOT NULL DEFAULT 0,
	pension_actual FLOAT NOT NULL DEFAULT 0,
	wallet_adjustment FLOAT NOT NULL DEFAULT 0,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	UNIQUE (account_id, stat_month),
	FOREIGN KEY (account_id) REFERENCES accounts(id)
);

CREATE TABLE net_worth_snapshots (
	id INT PRIMARY KEY AUTO_INCREMENT,
	account_id INT NOT NULL,
	snapshot_date DATE NOT NULL,
	card FLOAT NOT NULL DEFAULT 0,
	cash FLOAT NOT NULL DEFAULT 0,
	savings FLOAT NOT NULL DEFAULT 0,
	investments FLOAT NOT NULL DEFAULT 0,
	pension FLOAT NOT NULL DEFAULT 0,
	total FLOAT NOT NULL DEFAULT 0,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	UNIQUE (account_id, snapshot_date),
	FOREIGN KEY (account_id) REFERENCES accounts(id)
);

CREATE TABLE stock_goals (
	id INT PRIMARY KEY AUTO_INCREMENT,
	account_id INT NOT NULL,
	stock_note VARCHAR(150) NOT NULL,
	monthly_amount DECIMAL(10, 2) NOT NULL,
	effective_from DATE NOT NULL,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	UNIQUE (account_id, stock_note, effective_from),
	FOREIGN KEY (account_id) REFERENCES accounts(id)
);

CREATE TABLE stock_goal_colors (
	id INT PRIMARY KEY AUTO_INCREMENT,
	account_id INT NOT NULL,
	stock_key VARCHAR(150) NOT NULL,
	color CHAR(7) NOT NULL,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	UNIQUE (account_id, stock_key),
	FOREIGN KEY (account_id) REFERENCES accounts(id)
);