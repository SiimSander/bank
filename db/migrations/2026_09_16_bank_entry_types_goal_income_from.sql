ALTER TABLE bank_entry_types
	ADD COLUMN goal_income_from DATE NULL AFTER income_percent;

UPDATE bank_entry_types
SET goal_income_from = DATE(created_at)
WHERE income_percent IS NOT NULL
	AND slug NOT IN ('expenses', 'savings', 'investments', 'pension', 'income', 'wallet_adjustment', 'kogumiskonto');
