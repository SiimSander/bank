CREATE TABLE net_worth_snapshots (
	id INT PRIMARY KEY AUTO_INCREMENT,
	account_id INT NOT NULL,
	snapshot_date DATE NOT NULL,
	card FLOAT NOT NULL DEFAULT 0,
	cash FLOAT NOT NULL DEFAULT 0,
	savings FLOAT NOT NULL DEFAULT 0,
	investments FLOAT NOT NULL DEFAULT 0,
	total FLOAT NOT NULL DEFAULT 0,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	UNIQUE (account_id, snapshot_date),
	FOREIGN KEY (account_id) REFERENCES accounts(id)
);
