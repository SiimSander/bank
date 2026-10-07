<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class LhvMappingTest extends TestCase {
	public function testKogumiskontoInNote(): void {
		self::assertSame('kogumiskonto', lhvClassifyTransactionType('Transfer to kogumiskonto', 'DBIT'));
	}

	public function testSavingsInNote(): void {
		self::assertSame('savings', lhvClassifyTransactionType('Internal savings transfer', 'DBIT'));
	}

	public function testCreditDefaultsToIncome(): void {
		self::assertSame('income', lhvClassifyTransactionType('Salary payment', 'CRDT'));
	}

	public function testDebitDefaultsToExpenses(): void {
		self::assertSame('expenses', lhvClassifyTransactionType('Shop purchase', 'DBIT'));
	}

	public function testValjamakseMakesSavingsWithdrawalNegative(): void {
		self::assertSame(-120.0, lhvTransactionAmount('savings', 'Kogumiskonto väljamakse', 120.0));
	}

	public function testRegularSavingsAmountStaysPositive(): void {
		self::assertSame(120.0, lhvTransactionAmount('savings', 'Transfer to savings', 120.0));
	}
}
