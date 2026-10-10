<?php

const CSS_SOURCE_DIRECTORY = __DIR__ . '/../public/assets/css';

/**
 * Source files of /assets/css/index.css, in cascade order.
 * Later files override earlier ones with equal specificity, so never reorder without checking the pages.
 *
 * @return array<int, string>
 */
function cssBundleFiles(): array {
	return [
		'00-base.css',
		'01-layout.css',
		'02-buttons.css',
		'03-landing-auth.css',
		'04-bank-header.css',
		'05-forms.css',
		'06-bank-summary.css',
		'07-bank-entries.css',
		'08-entry-cards.css',
		'09-month-history.css',
		'10-modal-goals.css',
		'11-habits.css',
		'12-responsive.css',
		'13-bank-configure.css',
		'14-onboarding.css',
		'15-account-legal.css',
		'16-calculator.css',
		'17-stock-goals.css',
		'18-stock-logos.css',
	];
}

function buildCssBundle(): string {
	static $bundle = null;

	if ($bundle !== null) {
		return $bundle;
	}

	$bundle = '';

	foreach (cssBundleFiles() as $file) {
		$contents = (string) file_get_contents(CSS_SOURCE_DIRECTORY . '/' . $file);
		$bundle .= str_ends_with($contents, "\n") ? $contents : $contents . "\n";
	}

	return $bundle;
}

// Changes with every CSS edit, so a long browser cache never serves a stale stylesheet.
function cssBundleVersion(): string {
	return substr(md5(buildCssBundle()), 0, 12);
}
