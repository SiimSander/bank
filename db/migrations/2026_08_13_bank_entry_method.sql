ALTER TABLE bank_entries
	ADD COLUMN method ENUM('card', 'cash') NOT NULL DEFAULT 'card' AFTER type;

INSERT INTO bank_entries (account_id, entry_date, type, method, amount, note)
SELECT id, CURDATE(), 'wallet_adjustment', 'cash', cash_balance, 'Migrated from cash balance'
FROM accounts
WHERE cash_balance <> 0;

ALTER TABLE accounts
	DROP COLUMN cash_balance;
