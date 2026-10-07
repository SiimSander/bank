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
