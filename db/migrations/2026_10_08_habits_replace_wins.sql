CREATE TABLE IF NOT EXISTS habits (
	id INT PRIMARY KEY AUTO_INCREMENT,
	account_id INT NOT NULL,
	title VARCHAR(60) NOT NULL,
	position TINYINT UNSIGNED NOT NULL DEFAULT 1,
	created_date DATE NOT NULL,
	archived_from_month DATE NULL,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	INDEX idx_habits_account (account_id),
	FOREIGN KEY (account_id) REFERENCES accounts(id)
);

CREATE TABLE IF NOT EXISTS habit_logs (
	id INT PRIMARY KEY AUTO_INCREMENT,
	habit_id INT NOT NULL,
	log_date DATE NOT NULL,
	status ENUM('done', 'failed') NOT NULL,
	updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	UNIQUE (habit_id, log_date),
	FOREIGN KEY (habit_id) REFERENCES habits(id) ON DELETE CASCADE
);

DROP TABLE IF EXISTS win_card_items;
DROP TABLE IF EXISTS win_cards;
