<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\RateLimiter;
use PHPUnit\Framework\TestCase;

final class RateLimiterTest extends TestCase
{
    public function testBucketKey(): void
    {
        $this->assertSame('login:127.0.0.1', RateLimiter::bucketKey('login', '127.0.0.1'));
        $this->assertSame('forgot:unknown', RateLimiter::bucketKey('forgot', 'unknown'));
        $this->assertSame('pay:42', RateLimiter::bucketKey('pay', '42'));
    }
}
