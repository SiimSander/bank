ALTER TABLE monthly_stats
	ADD COLUMN wallet_adjustment FLOAT NOT NULL DEFAULT 0 AFTER investment_actual;
