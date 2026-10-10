<?php

const STOCK_TICKER_CURRENCY = '€';
const STOCK_NOTE_MAX_LENGTH = 150;

/**
 * Stock names the app knows a ticker for, so a user can type only the name. Names are written in lower case and
 * matched exactly after normalizeStockName(); the logo and colour follow from the ticker through STOCK_LOGO_ALIASES.
 */
const STOCK_NAME_TICKERS = [
	// vanguard
	'vanguard s&p 500' => 'VUAA',
	'vanguard ftse all-world' => 'VWCE',
	'vanguard ftse all-world high dividend yield' => 'VHYL',
	'vanguard ftse developed world' => 'VGVF',
	'vanguard ftse developed europe' => 'VEUR',
	'vanguard ftse 100' => 'VUKE',
	'vanguard eur eurozone government bond' => 'VGEA',
	'vanguard ftse emerging markets' => 'VDEM',
	'vanguard usd corporate bond' => 'VDPA',
	'vanguard ftse developed europe ex uk' => 'VERX',
	'vanguard eur corporate bond' => 'VECP',
	'vanguard ftse north america' => 'VNRT',
	'vanguard ftse japan' => 'VJPN',
	'vanguard global aggregate bond - eur hedged' => 'VAGF',
	'vanguard ftse developed asia pacific ex japan' => 'VAPX',
	'vanguard ftse 250' => 'VMID',
	'vanguard usd treasury bond' => 'VUTA',
	'vanguard esg global all cap' => 'V3AA',
	'vanguard global aggregate bond - usd hedged' => 'VAGU',
	// ishares
	'ishares core s&p 500' => 'CSPX',
	'ishares core msci world' => 'IWDA',
	'ishares core msci emerging markets imi' => 'EIMI',
	'ishares core msci emerging markets' => 'EMIM',
	'ishares msci acwi' => 'ISAC',
	'ishares nasdaq 100' => 'CNDX',
	'ishares s&p 500 information technology sector' => 'QDVE',
	'ishares core ftse 100' => 'ISFA',
	'ishares core msci europe' => 'IMAE',
	'ishares msci usa esg enhanced' => 'OM3L',
	'ishares msci usa screened' => 'SGAS',
	'ishares msci usa ctb enhanced esg' => 'EEDG',
	'ishares stoxx europe 600' => 'EXSA',
	'ishares core euro stoxx 50' => 'EXW1',
	'ishares msci em' => 'EUNM',
	'ishares msci world small cap' => 'WSML',
	'ishares s&p 500 eur hedged' => 'IBCF',
	'ishares core euro corporate bond' => 'IEAC',
	'ishares core dax' => 'EXS1',
	'ishares msci em asia' => 'CEBL',
	'ishares core msci japan' => 'IJPA',
	// invesco
	'invesco eqqq nasdaq 100' => 'EQQQ',
	'invesco' => 'IVZ',
	'invesco s&p 500' => 'SPXP',
	'invesco ftse all-world' => 'FWIA',
	'invesco coinshares global blockchain' => 'BCHS',
	// alphabet
	'alphabet class a' => 'GOOGL',
	'alphabet class c' => 'GOOG',
	// single stocks and crypto
	'ethereum' => 'ETH',
	'wise' => 'WISE',
	'bitcoin' => 'BTC',
	'apple' => 'AAPL',
	'nvidia' => 'NVDA',
	'amazon' => 'AMZN',
	'microsoft' => 'MSFT',
	'tesla' => 'TSLA',
	'infortar as' => 'INF1T',
	'micron technology' => 'MU',
	'mp materials' => 'MP',
];

/**
 * Lower case, one dash character and no spaces around a dash, so "Bond - EUR hedged" and "bond-eur hedged" match.
 */
function normalizeStockName(string $name): string {
	$name = mb_strtolower(trim($name));
	$name = str_replace(["\u{2010}", "\u{2011}", "\u{2012}", "\u{2013}", "\u{2014}", "\u{2212}"], '-', $name);
	$name = preg_replace('/\s*-\s*/u', '-', $name) ?? $name;

	return preg_replace('/\s+/u', ' ', $name) ?? $name;
}

/**
 * @return array<string, string> normalized stock name to ticker
 */
function getNormalizedStockNameTickers(): array {
	static $normalized = null;

	if ($normalized === null) {
		$normalized = [];

		foreach (STOCK_NAME_TICKERS as $name => $ticker) {
			$normalized[normalizeStockName($name)] = $ticker;
		}
	}

	return $normalized;
}

function lookupStockTicker(string $name): ?string {
	return getNormalizedStockNameTickers()[normalizeStockName($name)] ?? null;
}

/**
 * Normalized stock name to the lower-case ticker, for the logo suggestion in the add-stock form.
 *
 * @return array<string, string>
 */
function getStockNameLogoTickers(): array {
	return array_map('strtolower', getNormalizedStockNameTickers());
}

/**
 * Turns a name the user typed into the note the app stores. A ticker the user typed always wins; a stock the user
 * already has under that name keeps its stored note, so there is never a second copy with another ticker.
 */
function resolveStockNote(PDO $pdo, int $userId, string $note): string {
	$note = trim($note);

	if ($note === '' || splitInvestmentNote($note)['ticker'] !== '') {
		return $note;
	}

	$name = normalizeStockName($note);

	foreach (getInvestmentNotes($pdo, $userId) as $known) {
		if (normalizeStockName(splitInvestmentNote($known['note'])['name']) === $name) {
			return $known['note'];
		}
	}

	$ticker = lookupStockTicker($note);

	if ($ticker === null) {
		return $note;
	}

	$resolved = $note . ' (' . STOCK_TICKER_CURRENCY . $ticker . ')';

	return mb_strlen($resolved) > STOCK_NOTE_MAX_LENGTH ? $note : $resolved;
}

/**
 * Rewrites notes saved as a bare name to the full note, moving the stock's entries, goals, colour and logo choice to
 * it. A stock the account already has under that name with a ticker is the target, so the two merge.
 *
 * @return array<int, string> one line per rewritten note
 */
function backfillStockTickers(PDO $pdo, bool $dryRun = false): array {
	$accounts = $pdo->query(
		"SELECT account_id FROM bank_entries WHERE type = 'investments'
		UNION SELECT account_id FROM stock_goals"
	)->fetchAll(PDO::FETCH_COLUMN);

	$report = [];

	foreach ($accounts as $accountId) {
		$accountId = (int) $accountId;
		$notes = [];

		$entryNotes = $pdo->prepare(
			"SELECT DISTINCT note FROM bank_entries
			WHERE account_id = ? AND type = 'investments' AND note IS NOT NULL AND note <> ''"
		);
		$entryNotes->execute([$accountId]);
		$goalNotes = $pdo->prepare('SELECT DISTINCT stock_note FROM stock_goals WHERE account_id = ?');
		$goalNotes->execute([$accountId]);

		foreach (array_merge($entryNotes->fetchAll(PDO::FETCH_COLUMN), $goalNotes->fetchAll(PDO::FETCH_COLUMN)) as $note) {
			$notes[mb_strtolower(trim((string) $note))] ??= trim((string) $note);
		}

		$withTicker = [];
		foreach ($notes as $note) {
			$parts = splitInvestmentNote($note);

			if ($parts['ticker'] !== '') {
				$withTicker[normalizeStockName($parts['name'])] ??= $note;
			}
		}

		foreach ($notes as $oldKey => $note) {
			$oldKey = (string) $oldKey;
			if (splitInvestmentNote($note)['ticker'] !== '') {
				continue;
			}

			$name = normalizeStockName($note);
			$target = $withTicker[$name] ?? null;

			if ($target === null) {
				$ticker = lookupStockTicker($note);
				$full = $ticker === null ? null : $note . ' (' . STOCK_TICKER_CURRENCY . $ticker . ')';
				$target = $full !== null && mb_strlen($full) <= STOCK_NOTE_MAX_LENGTH ? $full : null;
			}

			if ($target === null) {
				continue;
			}

			$report[] = "account {$accountId}: \"{$note}\" -> \"{$target}\"";

			if (!$dryRun) {
				moveStockToNote($pdo, $accountId, $oldKey, $target);
			}
		}
	}

	return $report;
}

function moveStockToNote(PDO $pdo, int $accountId, string $oldKey, string $target): void {
	$newKey = mb_strtolower(trim($target));

	$pdo->beginTransaction();

	try {
		$pdo->prepare(
			"UPDATE bank_entries SET note = ?
			WHERE account_id = ? AND type = 'investments' AND LOWER(TRIM(note)) = ?"
		)->execute([$target, $accountId, $oldKey]);

		$goals = $pdo->prepare('SELECT id, effective_from FROM stock_goals WHERE account_id = ? AND LOWER(TRIM(stock_note)) = ?');
		$goals->execute([$accountId, $oldKey]);

		foreach ($goals->fetchAll() as $goal) {
			$taken = $pdo->prepare(
				'SELECT COUNT(*) FROM stock_goals WHERE account_id = ? AND LOWER(TRIM(stock_note)) = ? AND effective_from = ?'
			);
			$taken->execute([$accountId, $newKey, $goal['effective_from']]);

			if ((int) $taken->fetchColumn() > 0) {
				$pdo->prepare('DELETE FROM stock_goals WHERE id = ?')->execute([$goal['id']]);
			} else {
				$pdo->prepare('UPDATE stock_goals SET stock_note = ? WHERE id = ?')->execute([$target, $goal['id']]);
			}
		}

		foreach (['stock_goal_colors', 'stock_goal_logos'] as $table) {
			$exists = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE account_id = ? AND stock_key = ?");
			$exists->execute([$accountId, $newKey]);

			if ((int) $exists->fetchColumn() > 0) {
				$pdo->prepare("DELETE FROM {$table} WHERE account_id = ? AND stock_key = ?")->execute([$accountId, $oldKey]);
			} else {
				$pdo->prepare("UPDATE {$table} SET stock_key = ? WHERE account_id = ? AND stock_key = ?")
					->execute([$newKey, $accountId, $oldKey]);
			}
		}

		$pdo->commit();
	} catch (Throwable $e) {
		$pdo->rollBack();

		throw $e;
	}
}
