<?php

function computeCompoundInterestSchedule(
	float $startingPrincipal,
	float $monthlyContribution,
	float $annualReturnPercent,
	int $years,
	bool $contributionsAtStartOfMonth,
): array {
	$years = max(1, min(40, $years));
	$monthlyRate = $annualReturnPercent / 100 / 12;
	$totalMonths = $years * 12;
	$balance = $startingPrincipal;
	$schedule = [];

	$schedule[] = [
		'year' => 0,
		'totalInvested' => round($startingPrincipal, 2),
		'portfolioValue' => round($balance, 2),
	];

	for ($month = 1; $month <= $totalMonths; $month++) {
		if ($contributionsAtStartOfMonth) {
			$balance = ($balance + $monthlyContribution) * (1 + $monthlyRate);
		} else {
			$balance = ($balance * (1 + $monthlyRate)) + $monthlyContribution;
		}

		if ($month % 12 === 0) {
			$schedule[] = [
				'year' => (int) ($month / 12),
				'totalInvested' => round($startingPrincipal + ($monthlyContribution * $month), 2),
				'portfolioValue' => round($balance, 2),
			];
		}
	}

	return $schedule;
}

function computeCompoundInterestSummary(
	float $startingPrincipal,
	float $monthlyContribution,
	float $annualReturnPercent,
	int $years,
	bool $contributionsAtStartOfMonth,
): array {
	$schedule = computeCompoundInterestSchedule(
		$startingPrincipal,
		$monthlyContribution,
		$annualReturnPercent,
		$years,
		$contributionsAtStartOfMonth,
	);

	$final = $schedule[array_key_last($schedule)];

	return [
		'finalPortfolio' => $final['portfolioValue'],
		'totalCashInvested' => $final['totalInvested'],
		'totalInterestEarned' => round($final['portfolioValue'] - $final['totalInvested'], 2),
	];
}
