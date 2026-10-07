<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tests\Support\TypeFixtures;

final class BankEntryAmountTest extends TestCase {
	public function testPotDepositStaysPositive(): void {
		self::assertSame(50.0, normalizeBankEntryAmount(TypeFixtures::savings(), 'in', 50));
	}

	public function testPotWithdrawalBecomesNegative(): void {
		self::assertSame(-50.0, normalizeBankEntryAmount(TypeFixtures::savings(), 'out', 50));
	}

	public function testWalletOutRefundBecomesNegative(): void {
		self::assertSame(-50.0, normalizeBankEntryAmount(TypeFixtures::expenses(), 'out', 50));
	}

	public function testWalletInTypeCannotWithdraw(): void {
		$incomeType = ['slug' => 'income', 'label' => 'Income', 'balance_mode' => 'wallet_in'];

		self::assertNull(normalizeBankEntryAmount($incomeType, 'out', 50));
	}

	public function testZeroAmountIsRejected(): void {
		self::assertNull(normalizeBankEntryAmount(TypeFixtures::savings(), 'in', 0));
	}
}
