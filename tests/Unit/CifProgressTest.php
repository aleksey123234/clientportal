<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\CifProgress;
use PHPUnit\Framework\TestCase;

final class CifProgressTest extends TestCase
{
    public function testCompletedAtWhenFullyComplete(): void
    {
        $ts = CifProgress::completedAtForSave(100);
        $this->assertNotNull($ts);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $ts);
    }

    public function testCompletedAtNullWhenIncomplete(): void
    {
        $this->assertNull(CifProgress::completedAtForSave(99));
        $this->assertNull(CifProgress::completedAtForSave(0));
        $this->assertNull(CifProgress::completedAtForSave(50));
    }

    public function testNextCompletedAtKeepsExistingWhenIncomplete(): void
    {
        $existing = '2026-01-15 10:00:00';
        $this->assertSame($existing, CifProgress::nextCompletedAt(50, $existing, '2026-08-01 12:00:00'));
        $this->assertNull(CifProgress::nextCompletedAt(99, null, '2026-08-01 12:00:00'));
    }

    public function testNextCompletedAtSetsWhenFirstComplete(): void
    {
        $now = '2026-08-04 12:00:00';
        $this->assertSame($now, CifProgress::nextCompletedAt(100, null, $now));
        $this->assertSame($now, CifProgress::nextCompletedAt(100, '', $now));
    }

    public function testNextCompletedAtDoesNotShiftWhenAlreadyComplete(): void
    {
        $existing = '2026-01-15 10:00:00';
        $this->assertSame(
            $existing,
            CifProgress::nextCompletedAt(100, $existing, '2026-08-04 12:00:00')
        );
    }
}
