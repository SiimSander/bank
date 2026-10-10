<?php

require_once __DIR__ . '/bank.php';
require_once __DIR__ . '/stockGoals.php';

const STOCK_LOGO_DIRECTORY = __DIR__ . '/../public/assets/img/stock-logos';
const STOCK_LOGO_URL_PATH = '/assets/img/stock-logos';
const STOCK_LOGO_NONE = 'none';
const STOCK_LOGO_EXTENSIONS = ['svg', 'png', 'webp', 'jpg'];

/** Tickers that share another ticker's logo, such as the two Vanguard funds; a logo file named after the ticker wins. */
const STOCK_LOGO_ALIASES = [
	'iwda' => 'cspx',
	'sec0' => 'cspx',
	'cndx' => 'cspx',
	'emim' => 'cspx',
	'dfnd' => 'cspx',
	'qdve' => 'cspx',
	'sxr8' => 'cspx',
	'imae' => 'cspx',
	'magr' => 'cspx',
	'csp1' => 'cspx',
	'swda' => 'cspx',
	'eimi' => 'cspx',
	'isac' => 'cspx',
	'iusq' => 'cspx',
	'ssac' => 'cspx',
	'idus' => 'cspx',
	'cnx1' => 'cspx',
	'iusa' => 'cspx',
	'iuit' => 'cspx',
	'isfa' => 'cspx',
	'isf' => 'cspx',
	'om3l' => 'cspx',
	'sgas' => 'cspx',
	'iitu' => 'cspx',
	'eedg' => 'cspx',
	'gpsa' => 'cspx',
	'imeu' => 'cspx',
	'idem' => 'cspx',
	'exsa' => 'cspx',
	'exw1' => 'cspx',
	'eunm' => 'cspx',
	'isx5' => 'cspx',
	'iqqe' => 'cspx',
	'wsml' => 'cspx',
	'ibcf' => 'cspx',
	'ieac' => 'cspx',
	'exs1' => 'cspx',
	'iwrd' => 'cspx',
	'sema' => 'cspx',
	'csx5' => 'cspx',
	'ieem' => 'cspx',
	'iusn' => 'cspx',
	'cebl' => 'cspx',
	'ijpa' => 'cspx',
	'iebc' => 'cspx',
	'vwra' => 'vuaa',
	'vusd' => 'vuaa',
	'vwce' => 'vuaa',
	'vusa' => 'vuaa',
	'vwrp' => 'vuaa',
	'vwrd' => 'vuaa',
	'vuag' => 'vuaa',
	'vwrl' => 'vuaa',
	'vhyl' => 'vuaa',
	'vgvf' => 'vuaa',
	'vhvg' => 'vuaa',
	'veur' => 'vuaa',
	'vuke' => 'vuaa',
	'vgve' => 'vuaa',
	'vgea' => 'vuaa',
	'vdem' => 'vuaa',
	'veve' => 'vuaa',
	'vdpa' => 'vuaa',
	'verx' => 'vuaa',
	'vecp' => 'vuaa',
	'vfem' => 'vuaa',
	'vwcg' => 'vuaa',
	'vnrt' => 'vuaa',
	'vuce' => 'vuaa',
	'vjpn' => 'vuaa',
	'vnra' => 'vuaa',
	'veua' => 'vuaa',
	'vcpa' => 'vuaa',
	'vnrg' => 'vuaa',
	'vagf' => 'vuaa',
	'vapx' => 'vuaa',
	'vmid' => 'vuaa',
	'vgeb' => 'vuaa',
	'vety' => 'vuaa',
	'vuta' => 'vuaa',
	'v3aa' => 'vuaa',
	'v3ab' => 'vuaa',
	'vagu' => 'vuaa',
	'vgla' => 'vuaa',
];

/** Brand color of each logo slug; a stock with that logo and no color of its own gets it. */
const STOCK_LOGO_COLORS = [
	'vuaa' => '#9a0718',
	'wise' => '#9fe870',
	'eth' => '#627eea',
	'btc' => '#f7931a',
	'mp' => '#3952d0',
	'mu' => '#0077c8',
	'inf1t' => '#ffffff',
	'tsla' => '#e30526',
	'nvda' => '#272d2f',
	'msft' => '#ffffff',
	'aapl' => '#473449',
	'cspx' => '#66b833',
];

/**
 * Every image file in the logo folder is a logo; the lower-case file name without extension is its slug and
 * also the ticker it is suggested for, so `vuaa.png` is suggested for a stock noted as "(€VUAA)".
 *
 * @return array<string, array{slug: string, label: string, url: string}>
 */
function getStockLogoCatalog(?string $directory = null): array {
	static $cache = [];

	$directory ??= STOCK_LOGO_DIRECTORY;

	if (isset($cache[$directory])) {
		return $cache[$directory];
	}

	$catalog = [];

	foreach (STOCK_LOGO_EXTENSIONS as $extension) {
		foreach (glob($directory . '/*.' . $extension) ?: [] as $path) {
			$slug = strtolower(pathinfo($path, PATHINFO_FILENAME));

			if (preg_match('/^[a-z0-9-]+$/', $slug) !== 1 || isset($catalog[$slug])) {
				continue;
			}

			$catalog[$slug] = [
				'slug' => $slug,
				'label' => strtoupper($slug),
				'url' => STOCK_LOGO_URL_PATH . '/' . basename($path) . '?v=' . (int) filemtime($path),
			];
		}
	}

	ksort($catalog);

	return $cache[$directory] = $catalog;
}

/**
 * The catalog slug a stock note's ticker points at: "Vanguard S&P 500 (€VUAA)" gives "vuaa".
 */
function stockLogoSlugForNote(string $note): string {
	$ticker = trim(splitInvestmentNote($note)['ticker'], '()');
	$slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($ticker)) ?? '';

	return trim($slug, '-');
}

/**
 * @return array<string, string> stock key to the logo slug the user chose, or STOCK_LOGO_NONE for no logo
 */
function getStoredStockLogos(PDO $pdo, int $userId): array {
	$rawSql = $pdo->prepare('SELECT stock_key, logo_slug FROM stock_goal_logos WHERE account_id = ?');
	$rawSql->execute([$userId]);

	$logos = [];
	foreach ($rawSql->fetchAll() as $row) {
		$logos[(string) $row['stock_key']] = (string) $row['logo_slug'];
	}

	return $logos;
}

/**
 * A stored choice wins; a stock without one gets the logo that matches its ticker.
 *
 * @param array<string, string> $stored
 * @param array<string, array{slug: string, label: string, url: string}> $catalog
 */
function resolveStockLogoSlug(string $note, array $stored, array $catalog): ?string {
	$choice = $stored[stockGoalKey($note)] ?? null;

	if ($choice === STOCK_LOGO_NONE) {
		return null;
	}

	if ($choice !== null && isset($catalog[$choice])) {
		return $choice;
	}

	return suggestedStockLogoSlug($note, $catalog);
}

/**
 * The logo a ticker points at: a file named after the ticker, otherwise the logo it is an alias of.
 *
 * @param array<string, array{slug: string, label: string, url: string}> $catalog
 */
function suggestedStockLogoSlug(string $note, array $catalog): ?string {
	$slug = stockLogoSlugForNote($note);

	if (isset($catalog[$slug])) {
		return $slug;
	}

	$aliasTarget = STOCK_LOGO_ALIASES[$slug] ?? '';

	return isset($catalog[$aliasTarget]) ? $aliasTarget : null;
}

function stockLogoColor(?string $slug): ?string {
	return $slug === null ? null : (STOCK_LOGO_COLORS[$slug] ?? null);
}

/**
 * The brand colour for a stock that has no stored logo choice yet: the slug picked in the form when there is one,
 * otherwise the logo its ticker matches.
 */
function stockLogoColorForNewStock(string $note, ?string $chosenSlug, ?string $logoDirectory = null): ?string {
	$catalog = getStockLogoCatalog($logoDirectory);
	$chosenSlug = $chosenSlug === null ? '' : strtolower(trim($chosenSlug));

	if ($chosenSlug === STOCK_LOGO_NONE) {
		return null;
	}

	$slug = $chosenSlug !== '' && isset($catalog[$chosenSlug]) ? $chosenSlug : suggestedStockLogoSlug($note, $catalog);

	return stockLogoColor($slug);
}

/**
 * Loads the user's logo choices once and returns a lookup from a stock note to its logo.
 *
 * @return Closure(string): (array{slug: string, url: string}|null)
 */
function stockLogoResolver(PDO $pdo, int $userId, ?string $logoDirectory = null): Closure {
	$catalog = getStockLogoCatalog($logoDirectory);

	if ($catalog === []) {
		return static fn(string $note): ?array => null;
	}

	$stored = getStoredStockLogos($pdo, $userId);

	return static function (string $note) use ($catalog, $stored): ?array {
		$slug = resolveStockLogoSlug($note, $stored, $catalog);

		return $slug === null ? null : ['slug' => $slug, 'url' => $catalog[$slug]['url']];
	};
}

/**
 * @return true|string true on success, otherwise an error message
 */
function setStockLogo(PDO $pdo, int $userId, string $note, string $slug, ?string $logoDirectory = null): bool|string {
	$key = stockGoalKey($note);
	$slug = strtolower(trim($slug));

	if ($slug !== STOCK_LOGO_NONE && !isset(getStockLogoCatalog($logoDirectory)[$slug])) {
		return 'Choose a logo from the list.';
	}

	if (!isset(getStockNoteSpellings($pdo, $userId)[$key]) && !isset(getStockGoalHistory($pdo, $userId)[$key])) {
		return 'Stock not found.';
	}

	$rawSql = $pdo->prepare(
		'INSERT INTO stock_goal_logos (account_id, stock_key, logo_slug) VALUES (?, ?, ?)
		ON DUPLICATE KEY UPDATE logo_slug = VALUES(logo_slug)'
	);
	$rawSql->execute([$userId, $key, $slug]);

	return true;
}

/**
 * The share of the whole portfolio each stock holds, based on everything ever invested in it.
 * Only money put in counts, so a share is never negative.
 *
 * @return array{total: float, items: array<int, array<string, mixed>>}
 */
function getStockPortfolio(PDO $pdo, int $userId, ?string $logoDirectory = null): array {
	$investedByMonth = getStockInvestedByMonth($pdo, $userId);
	$spellings = getStockNoteSpellings($pdo, $userId);
	$colors = getStockColors($pdo, $userId, getStockGoalHistory($pdo, $userId));
	$logoFor = stockLogoResolver($pdo, $userId, $logoDirectory);

	$totals = [];
	foreach ($investedByMonth as $month) {
		foreach ($month as $key => $amount) {
			$totals[$key] = ($totals[$key] ?? 0.0) + $amount;
		}
	}

	$totals = array_filter(array_map(fn(float $amount) => round($amount, 2), $totals), fn(float $amount) => $amount > 0);
	$grandTotal = round(array_sum($totals), 2);

	if ($grandTotal <= 0) {
		return ['total' => 0.0, 'items' => []];
	}

	arsort($totals);

	$items = [];
	foreach ($totals as $key => $invested) {
		$note = $spellings[$key] ?? (string) $key;
		$parts = splitInvestmentNote($note);
		$logo = $logoFor($note);

		$items[] = [
			'key' => (string) $key,
			'note' => $note,
			'name' => $parts['name'],
			'ticker' => $parts['ticker'],
			'color' => $colors[$key] ?? stockLogoColor($logo['slug'] ?? null),
			'logo' => $logo['url'] ?? null,
			'invested' => $invested,
			'share' => round($invested / $grandTotal * 100, 2),
		];
	}

	return ['total' => $grandTotal, 'items' => $items];
}

function stockLogoImage(?string $url, string $extraClass = ''): string {
	if ($url === null || $url === '') {
		return '';
	}

	return '<img class="stock-logo' . ($extraClass !== '' ? ' ' . htmlspecialchars($extraClass, ENT_QUOTES) : '') . '"'
		. ' src="' . htmlspecialchars($url, ENT_QUOTES) . '" alt="" width="64" height="64" loading="lazy" decoding="async">';
}
