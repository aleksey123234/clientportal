<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\PaymentFields;
use PHPUnit\Framework\TestCase;

final class PaymentFieldsTest extends TestCase
{
    public function testAcceptsValidPanExpiryAndCvd(): void
    {
        $this->assertTrue(PaymentFields::isValid('4111111111111', '04/2026', '123'));
        $this->assertTrue(PaymentFields::isValid('4111111111111111', '04/2026', '1234'));
    }

    public function testRejectsShortPan(): void
    {
        $this->assertFalse(PaymentFields::isValid('411111111111', '04/2026', '123'));
    }

    public function testRejectsEmptyExpiry(): void
    {
        $this->assertFalse(PaymentFields::isValid('4111111111111', '', '123'));
        $this->assertFalse(PaymentFields::isValid('4111111111111', '   ', '123'));
    }

    public function testRejectsBadCvdLength(): void
    {
        $this->assertFalse(PaymentFields::isValid('4111111111111', '04/2026', ''));
        $this->assertFalse(PaymentFields::isValid('4111111111111', '04/2026', '12'));
        $this->assertFalse(PaymentFields::isValid('4111111111111', '04/2026', '12345'));
    }
}
