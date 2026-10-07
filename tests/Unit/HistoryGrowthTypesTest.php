<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tests\Support\TypeFixtures;

final class HistoryGrowthTypesTest extends TestCase {
	public function testIncludesPotTypesOnly(): void {
		$historyTypes = [
			TypeFixtures::expenses(),
			TypeFixtures::savings(),
			TypeFixtures::investments(),
			TypeFixtures::dept(),
			TypeFixtures::pension(),
		];

		$growthSlugs = array_column(getHistoryGrowthTypes($historyTypes), 'slug');

		self::assertSame(['savings', 'investments', 'pension'], $growthSlugs);
	}
}
