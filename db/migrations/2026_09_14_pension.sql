ALTER TABLE bank_entries
	MODIFY COLUMN type ENUM('income', 'expenses', 'investments', 'wallet_adjustment', 'savings', 'kogumiskonto', 'pension') NOT NULL;

ALTER TABLE monthly_stats
	ADD COLUMN pension_actual FLOAT NOT NULL DEFAULT 0 AFTER kogumiskonto_actual;

ALTER TABLE net_worth_snapshots
	ADD COLUMN pension FLOAT NOT NULL DEFAULT 0 AFTER investments;
