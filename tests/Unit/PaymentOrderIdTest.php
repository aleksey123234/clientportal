<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\PaymentOrderId;
use PHPUnit\Framework\TestCase;

final class PaymentOrderIdTest extends TestCase
{
    public function testBuildIsStable(): void
    {
        $a = PaymentOrderId::build(42, [3, 1, 2]);
        $b = PaymentOrderId::build(42, [3, 1, 2]);
        $this->assertSame($a, $b);
        $this->assertStringStartsWith('portal_u42_', $a);
        $this->assertMatchesRegularExpression('/^portal_u42_[a-f0-9]{16}$/', $a);
    }

    public function testBuildSortsIdsSoOrderDoesNotMatter(): void
    {
        $a = PaymentOrderId::build(7, [10, 2, 5]);
        $b = PaymentOrderId::build(7, [5, 10, 2]);
        $c = PaymentOrderId::build(7, [2, 5, 10, 10]);
        $this->assertSame($a, $b);
        $this->assertSame($a, $c);
    }

    public function testDifferentUsersOrIdsDiffer(): void
    {
        $a = PaymentOrderId::build(1, [1, 2]);
        $b = PaymentOrderId::build(2, [1, 2]);
        $c = PaymentOrderId::build(1, [1, 3]);
        $this->assertNotSame($a, $b);
        $this->assertNotSame($a, $c);
    }
}
