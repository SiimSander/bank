<?php

const HABIT_CHART_SIZE = 660;
const HABIT_CHART_OUTER_RADIUS = 262;
const HABIT_CHART_HOLE_RADIUS = 92;
const HABIT_CHART_SWEEP_DEGREES = 270.0;
const HABIT_CHART_LABEL_MAX_FONT = 18;

/** @return array{0: float, 1: float} */
function habitChartPoint(float $centerX, float $centerY, float $radius, float $degrees): array {
	$radians = deg2rad($degrees);

	return [
		$centerX + $radius * sin($radians),
		$centerY - $radius * cos($radians),
	];
}

function habitChartSectorPath(float $centerX, float $centerY, float $innerRadius, float $outerRadius, float $startDegrees, float $endDegrees): string {
	[$outerStartX, $outerStartY] = habitChartPoint($centerX, $centerY, $outerRadius, $startDegrees);
	[$outerEndX, $outerEndY] = habitChartPoint($centerX, $centerY, $outerRadius, $endDegrees);
	[$innerEndX, $innerEndY] = habitChartPoint($centerX, $centerY, $innerRadius, $endDegrees);
	[$innerStartX, $innerStartY] = habitChartPoint($centerX, $centerY, $innerRadius, $startDegrees);

	return sprintf(
		'M%.2f %.2f A%.2f %.2f 0 0 1 %.2f %.2f L%.2f %.2f A%.2f %.2f 0 0 0 %.2f %.2f Z',
		$outerStartX,
		$outerStartY,
		$outerRadius,
		$outerRadius,
		$outerEndX,
		$outerEndY,
		$innerEndX,
		$innerEndY,
		$innerRadius,
		$innerRadius,
		$innerStartX,
		$innerStartY
	);
}

function habitChartTruncate(string $text, int $maxCharacters): string {
	if (mb_strlen($text) <= $maxCharacters) {
		return $text;
	}

	return rtrim(mb_substr($text, 0, $maxCharacters - 1)) . '…';
}

/**
 * Habit 1 is the outermost ring. Days run clockwise from 12 o'clock over 270 degrees, which leaves
 * the top-left quarter free for the habit names.
 *
 * @param array{month_start: string, days: int, habits: list<array{id: int, title: string, statuses: array<int, string>}>} $month
 */
function renderHabitChartSvg(array $month, bool $highlightToday = false): string {
	$center = HABIT_CHART_SIZE / 2;
	$habitCount = count($month['habits']);
	$days = $month['days'];
	$today = Clock::today();
	$slotDegrees = HABIT_CHART_SWEEP_DEGREES / $days;
	$ringThickness = $habitCount > 0
		? (HABIT_CHART_OUTER_RADIUS - HABIT_CHART_HOLE_RADIUS) / $habitCount
		: 0.0;
	$monthLabel = date('F', strtotime($month['month_start']));
	$yearLabel = date('Y', strtotime($month['month_start']));
	$ariaLabel = $monthLabel . ' ' . $yearLabel . ' habit chart';

	$svg = sprintf(
		'<svg class="habit-chart" viewBox="0 0 %1$d %1$d" role="img" aria-label="%2$s" xmlns="http://www.w3.org/2000/svg">',
		HABIT_CHART_SIZE,
		htmlspecialchars($ariaLabel, ENT_QUOTES)
	);

	foreach ($month['habits'] as $index => $habit) {
		$outerRadius = HABIT_CHART_OUTER_RADIUS - $index * $ringThickness;
		$innerRadius = $outerRadius - $ringThickness;

		for ($day = 1; $day <= $days; $day++) {
			$date = date('Y-m-d', strtotime($month['month_start'] . ' +' . ($day - 1) . ' days'));
			$status = $habit['statuses'][$day] ?? HABIT_STATUS_NONE;
			$cellClass = $status === HABIT_STATUS_PENDING && $date > $today ? 'future' : $status;
			$startDegrees = ($day - 1) * $slotDegrees;
			$endDegrees = $day * $slotDegrees;
			$cellTitle = $habit['title'] . ' - ' . date('M j', strtotime($date)) . ': ' . $status;

			$svg .= sprintf(
				'<path class="habit-chart__cell habit-chart__cell--%s" data-habit-id="%d" data-date="%s" d="%s"><title>%s</title></path>',
				$cellClass,
				$habit['id'],
				$date,
				habitChartSectorPath($center, $center, $innerRadius, $outerRadius, $startDegrees, $endDegrees),
				htmlspecialchars($cellTitle, ENT_QUOTES)
			);
		}
	}

	if ($highlightToday && habitMonthStart($today) === $month['month_start'] && $habitCount > 0) {
		$todayDay = (int) date('j', strtotime($today));
		$svg .= sprintf(
			'<path class="habit-chart__today" d="%s"/>',
			habitChartSectorPath(
				$center,
				$center,
				HABIT_CHART_HOLE_RADIUS,
				HABIT_CHART_OUTER_RADIUS,
				($todayDay - 1) * $slotDegrees,
				$todayDay * $slotDegrees
			)
		);
	}

	$labelRadius = HABIT_CHART_OUTER_RADIUS + 18;

	for ($day = 1; $day <= $days; $day++) {
		[$labelX, $labelY] = habitChartPoint($center, $center, $labelRadius, ($day - 0.5) * $slotDegrees);
		$svg .= sprintf(
			'<text class="habit-chart__day" x="%.2f" y="%.2f" text-anchor="middle" dominant-baseline="central">%d</text>',
			$labelX,
			$labelY,
			$day
		);
	}

	$svg .= sprintf(
		'<text class="habit-chart__month" x="%1$d" y="%2$d" text-anchor="middle">%3$s</text>'
		. '<text class="habit-chart__year" x="%1$d" y="%4$d" text-anchor="middle">%5$s</text>',
		$center,
		$center - 6,
		htmlspecialchars(mb_strtoupper($monthLabel), ENT_QUOTES),
		$center + 26,
		htmlspecialchars($yearLabel, ENT_QUOTES)
	);

	$fontSize = $habitCount > 0 ? min(HABIT_CHART_LABEL_MAX_FONT, $ringThickness * 0.8) : HABIT_CHART_LABEL_MAX_FONT;
	$maxCharacters = max(8, (int) floor(($center - 24) / ($fontSize * 0.56)));

	foreach ($month['habits'] as $index => $habit) {
		$middleRadius = HABIT_CHART_OUTER_RADIUS - ($index + 0.5) * $ringThickness;
		$lineY = $center - $middleRadius + $fontSize * 0.45;
		$label = ($index + 1) . '. ' . $habit['title'];

		$svg .= sprintf(
			'<text class="habit-chart__label" x="10" y="%.2f" font-size="%.1f">%s</text>'
			. '<line class="habit-chart__leader" x1="8" y1="%.2f" x2="%d" y2="%.2f"/>',
			$lineY - 3,
			$fontSize,
			htmlspecialchars(habitChartTruncate($label, $maxCharacters), ENT_QUOTES),
			$lineY,
			$center,
			$lineY
		);
	}

	return $svg . '</svg>';
}
