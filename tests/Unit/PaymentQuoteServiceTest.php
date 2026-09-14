<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\PaymentQuoteService;
use PHPUnit\Framework\TestCase;

final class PaymentQuoteServiceTest extends TestCase
{
    public function testResolveProvinceCodeOn(): void
    {
        $this->assertSame('Ontario', PaymentQuoteService::resolveProvince('ON'));
        $this->assertSame('Ontario', PaymentQuoteService::resolveProvince('on'));
    }

    public function testResolveProvinceFullName(): void
    {
        $this->assertSame('Quebec', PaymentQuoteService::resolveProvince('Quebec'));
        $this->assertSame('Quebec', PaymentQuoteService::resolveProvince('quebec'));
    }

    public function testResolveProvinceEmptyOrUnknownDefaultsOntario(): void
    {
        $this->assertSame('Ontario', PaymentQuoteService::resolveProvince(null));
        $this->assertSame('Ontario', PaymentQuoteService::resolveProvince(''));
        $this->assertSame('Ontario', PaymentQuoteService::resolveProvince('Mars'));
    }

    public function testQuoteOntarioMultiRow(): void
    {
        $q = PaymentQuoteService::quote([
            ['amount' => 100],
            ['amount' => 50.50],
        ], 'ON');

        $this->assertSame(150.5, $q['subtotal']);
        $this->assertSame(0.13, $q['tax_rate']);
        $this->assertEqualsWithDelta(19.57, $q['tax'], 0.001);
        $this->assertEqualsWithDelta(170.07, $q['total'], 0.001);
    }

    public function testQuoteQuebecRounding(): void
    {
        $q = PaymentQuoteService::quote([['amount' => 100]], 'QC');
        $this->assertSame(0.14975, $q['tax_rate']);
        $this->assertEqualsWithDelta(114.98, $q['total'], 0.001);
        $this->assertEqualsWithDelta(14.98, $q['tax'], 0.001);
    }

    public function testQuoteEmptyRows(): void
    {
        $q = PaymentQuoteService::quote([], 'AB');
        $this->assertSame(0.0, $q['subtotal']);
        $this->assertSame(0.0, $q['tax']);
        $this->assertSame(0.0, $q['total']);
        $this->assertSame(0.05, $q['tax_rate']);
    }
}
