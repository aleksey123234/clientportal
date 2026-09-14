<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Csrf;
use PHPUnit\Framework\TestCase;

final class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testEnsureTokenCreatesAndReuses(): void
    {
        $t1 = Csrf::ensureToken();
        $this->assertNotSame('', $t1);
        $this->assertSame(64, strlen($t1));
        $t2 = Csrf::ensureToken();
        $this->assertSame($t1, $t2);
    }

    public function testValidateMatch(): void
    {
        $token = Csrf::ensureToken();
        $this->assertTrue(Csrf::validate($token));
    }

    public function testValidateMismatchAndEmpty(): void
    {
        Csrf::ensureToken();
        $this->assertFalse(Csrf::validate('wrong'));
        $this->assertFalse(Csrf::validate(''));
        $this->assertFalse(Csrf::validate(null));
    }

    public function testValidateFailsWhenSessionEmpty(): void
    {
        $this->assertFalse(Csrf::validate('anything'));
    }
}
