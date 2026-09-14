<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\RegistrationToken;
use PHPUnit\Framework\TestCase;

final class RegistrationTokenTest extends TestCase
{
    public function testValidUuidShape(): void
    {
        $this->assertTrue(RegistrationToken::isValidFormat(
            '550e8400-e29b-41d4-a716-446655440000'
        ));
        $this->assertTrue(RegistrationToken::isValidFormat(
            'AAAAAAAA-BBBB-CCCC-DDDD-EEEEEEEEEEEE'
        ));
    }

    public function testRejectsEmptyAndGarbage(): void
    {
        $this->assertFalse(RegistrationToken::isValidFormat(''));
        $this->assertFalse(RegistrationToken::isValidFormat('not-a-uuid'));
        $this->assertFalse(RegistrationToken::isValidFormat('550e8400e29b41d4a716446655440000'));
        $this->assertFalse(RegistrationToken::isValidFormat('550e8400-e29b-41d4-a716-44665544000'));
    }
}
