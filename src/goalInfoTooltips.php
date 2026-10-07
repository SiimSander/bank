<?php

function getGoalInfoPeriodPhrase(string $period): string {
	return match ($period) {
		'today' => 'today',
		'week' => 'this week',
		'month' => 'this month',
		'history' => 'that month',
		default => 'this month',
	};
}

function getGoalInfoTooltipText(string $slug, string $variant, string $period = 'month'): string {
	$templates = [
		'expenses' => [
			'should' => "How much you should've spent {period}",
			'actual' => 'How much you have really spent {period}',
		],
		'savings' => [
			'should' => "How much you should've save {period}",
			'actual' => 'How much you have really saved {period}',
		],
		'investments' => [
			'should' => "How much you should've invest {period}",
			'actual' => 'How much you have really invested {period}',
		],
		'debt' => [
			'should' => "How much you should've pay debt {period}",
			'actual' => 'How much you have really payed dept {period}',
		],
	];

	$template = $templates[$slug][$variant] ?? null;

	if ($template === null) {
		return 'text';
	}

	return str_replace('{period}', getGoalInfoPeriodPhrase($period), $template);
}
