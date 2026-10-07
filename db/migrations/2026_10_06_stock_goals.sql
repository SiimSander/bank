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
