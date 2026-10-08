<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class CssBundleTest extends TestCase {
	public function testEveryListedFileExistsAndHasRules(): void {
		foreach (cssBundleFiles() as $file) {
			$path = CSS_SOURCE_DIRECTORY . '/' . $file;

			self::assertFileExists($path);
			self::assertStringContainsString('{', (string) file_get_contents($path), $file);
		}
	}

	public function testNoFileIsListedTwice(): void {
		self::assertSame(cssBundleFiles(), array_values(array_unique(cssBundleFiles())));
	}

	public function testFilesAreListedInTheirNumericOrder(): void {
		$sorted = cssBundleFiles();
		sort($sorted, SORT_STRING);

		self::assertSame($sorted, cssBundleFiles());
	}

	public function testEveryCssFileInTheFolderIsListed(): void {
		$onDisk = array_map('basename', glob(CSS_SOURCE_DIRECTORY . '/*.css') ?: []);
		sort($onDisk, SORT_STRING);

		self::assertSame($onDisk, cssBundleFiles());
	}

	public function testBundleKeepsTokensFirstAndStockGoalsLast(): void {
		$bundle = buildCssBundle();

		self::assertStringStartsWith('/* Base', $bundle);
		self::assertGreaterThan(strpos($bundle, ':root'), strpos($bundle, '.stock-goals {'));
		self::assertStringEndsWith("\n", $bundle);
	}

	public function testVersionChangesOnlyWithContent(): void {
		self::assertSame(cssBundleVersion(), cssBundleVersion());
		self::assertSame(12, strlen(cssBundleVersion()));
	}
}
