ALTER TABLE accounts
	ADD COLUMN opening_balances_set_at TIMESTAMP NULL AFTER guaranteed_monthly_income;
