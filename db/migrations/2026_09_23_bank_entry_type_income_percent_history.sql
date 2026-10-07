CREATE TABLE bank_entry_type_income_percent_history (
	id INT PRIMARY KEY AUTO_INCREMENT,
	bank_entry_type_id INT NOT NULL,
	effective_from DATE NOT NULL,
	income_percent DECIMAL(5, 4) NOT NULL,
	UNIQUE (bank_entry_type_id, effective_from),
	FOREIGN KEY (bank_entry_type_id) REFERENCES bank_entry_types(id) ON DELETE CASCADE
);

INSERT INTO bank_entry_type_income_percent_history (bank_entry_type_id, effective_from, income_percent)
SELECT id, DATE(created_at), income_percent
FROM bank_entry_types
WHERE income_percent IS NOT NULL;
