CREATE TABLE bank_entry_types (
	id INT PRIMARY KEY AUTO_INCREMENT,
	account_id INT NOT NULL,
	slug VARCHAR(64) NOT NULL,
	label VARCHAR(100) NOT NULL,
	color_hex CHAR(7) NOT NULL,
	income_percent DECIMAL(5, 4) NULL,
	balance_mode ENUM('wallet_in', 'wallet_out', 'pot', 'adjustment') NOT NULL,
	is_active TINYINT(1) NOT NULL DEFAULT 1,
	is_system TINYINT(1) NOT NULL DEFAULT 0,
	show_in_pills TINYINT(1) NOT NULL DEFAULT 1,
	sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	UNIQUE (account_id, slug),
	FOREIGN KEY (account_id) REFERENCES accounts(id)
);

CREATE TABLE monthly_stat_by_type (
	account_id INT NOT NULL,
	stat_month DATE NOT NULL,
	type_slug VARCHAR(64) NOT NULL,
	goal_amount FLOAT NOT NULL DEFAULT 0,
	actual_amount FLOAT NOT NULL DEFAULT 0,
	PRIMARY KEY (account_id, stat_month, type_slug),
	FOREIGN KEY (account_id) REFERENCES accounts(id)
);

ALTER TABLE bank_entries MODIFY type VARCHAR(64) NOT NULL;

INSERT INTO bank_entry_types (account_id, slug, label, color_hex, income_percent, balance_mode, is_active, is_system, show_in_pills, sort_order)
SELECT a.id, t.slug, t.label, t.color_hex, t.income_percent, t.balance_mode, 1, t.is_system, t.show_in_pills, t.sort_order
FROM accounts a
CROSS JOIN (
	SELECT 'income' AS slug, 'Income' AS label, '#22c55e' AS color_hex, NULL AS income_percent, 'wallet_in' AS balance_mode, 1 AS is_system, 1 AS show_in_pills, 0 AS sort_order
	UNION ALL SELECT 'expenses', 'Expenses', '#f472b6', 0.6000, 'wallet_out', 0, 1, 1
	UNION ALL SELECT 'savings', 'Savings', '#60a5fa', 0.1500, 'pot', 0, 1, 2
	UNION ALL SELECT 'investments', 'Investments', '#a78bfa', 0.2500, 'pot', 0, 1, 3
	UNION ALL SELECT 'pension', 'Pension', '#fb923c', NULL, 'pot', 0, 1, 4
	UNION ALL SELECT 'kogumiskonto', 'Kogumiskonto', '#888888', NULL, 'pot', 1, 0, 5
	UNION ALL SELECT 'wallet_adjustment', 'Balance adjustment', '#888888', NULL, 'adjustment', 1, 0, 6
) t
WHERE NOT EXISTS (
	SELECT 1 FROM bank_entry_types bet WHERE bet.account_id = a.id AND bet.slug = t.slug
);

INSERT INTO monthly_stat_by_type (account_id, stat_month, type_slug, goal_amount, actual_amount)
SELECT ms.account_id, ms.stat_month, 'expenses', ms.essential_expenses_goal, ms.expenses_actual
FROM monthly_stats ms
WHERE ms.essential_expenses_goal <> 0 OR ms.expenses_actual <> 0
ON DUPLICATE KEY UPDATE goal_amount = VALUES(goal_amount), actual_amount = VALUES(actual_amount);

INSERT INTO monthly_stat_by_type (account_id, stat_month, type_slug, goal_amount, actual_amount)
SELECT ms.account_id, ms.stat_month, 'savings', ms.saving_goal, ms.saving_actual
FROM monthly_stats ms
WHERE ms.saving_goal <> 0 OR ms.saving_actual <> 0
ON DUPLICATE KEY UPDATE goal_amount = VALUES(goal_amount), actual_amount = VALUES(actual_amount);

INSERT INTO monthly_stat_by_type (account_id, stat_month, type_slug, goal_amount, actual_amount)
SELECT ms.account_id, ms.stat_month, 'investments', ms.investment_goal, ms.investment_actual
FROM monthly_stats ms
WHERE ms.investment_goal <> 0 OR ms.investment_actual <> 0
ON DUPLICATE KEY UPDATE goal_amount = VALUES(goal_amount), actual_amount = VALUES(actual_amount);

INSERT INTO monthly_stat_by_type (account_id, stat_month, type_slug, goal_amount, actual_amount)
SELECT ms.account_id, ms.stat_month, 'pension', 0, ms.pension_actual
FROM monthly_stats ms
WHERE ms.pension_actual <> 0
ON DUPLICATE KEY UPDATE actual_amount = VALUES(actual_amount);

INSERT INTO monthly_stat_by_type (account_id, stat_month, type_slug, goal_amount, actual_amount)
SELECT ms.account_id, ms.stat_month, 'kogumiskonto', 0, ms.kogumiskonto_actual
FROM monthly_stats ms
WHERE ms.kogumiskonto_actual <> 0
ON DUPLICATE KEY UPDATE actual_amount = VALUES(actual_amount);
