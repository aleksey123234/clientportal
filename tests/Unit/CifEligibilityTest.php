<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\CifEligibility;
use PHPUnit\Framework\TestCase;

final class CifEligibilityTest extends TestCase
{
    public function testHasWaiverAndCrHelpers(): void
    {
        $this->assertTrue(CifEligibility::hasWaiver(['waiver']));
        $this->assertTrue(CifEligibility::hasWaiver(['waiver-renewal']));
        $this->assertFalse(CifEligibility::hasWaiver(['criminal-rehab']));
        $this->assertTrue(CifEligibility::hasCrOrTrp(['trp']));
        $this->assertTrue(CifEligibility::isSplitCitizenshipUi(['waiver']));
    }

    public function testCrRequiresUsYesAndCanadianNo(): void
    {
        $ok = CifEligibility::validate(
            ['criminal-rehab'],
            ['us_citizenship' => 'Yes', 'canadian_citizenship' => 'No']
        );
        $this->assertSame([], $ok);

        $bad = CifEligibility::validate(
            ['criminal-rehab'],
            ['us_citizenship' => 'No', 'canadian_citizenship' => 'Yes']
        );
        $keys = CifEligibility::keys($bad);
        $this->assertContains('us_citizenship', $keys);
        $this->assertContains('canadian_citizenship', $keys);
    }

    public function testWaiverRequiresUsNoCanadianYesGreenCardNo(): void
    {
        $ok = CifEligibility::validate(
            ['waiver'],
            [
                'us_citizenship' => 'No',
                'canadian_citizenship' => 'Yes',
                'green_card' => 'No',
            ]
        );
        $this->assertSame([], $ok);

        $bad = CifEligibility::validate(
            ['waiver'],
            [
                'us_citizenship' => 'Yes',
                'canadian_citizenship' => 'No',
                'green_card' => 'Yes',
            ]
        );
        $keys = CifEligibility::keys($bad);
        $this->assertContains('us_citizenship', $keys);
        $this->assertContains('canadian_citizenship', $keys);
        $this->assertContains('green_card', $keys);
        $this->assertSame(
            [CifEligibility::CLIENT_CARE_MESSAGE],
            CifEligibility::messages($bad)
        );
    }

    public function testEmptyAnswersSkipped(): void
    {
        $errors = CifEligibility::validate(['waiver'], []);
        $this->assertSame([], $errors);
    }
}
