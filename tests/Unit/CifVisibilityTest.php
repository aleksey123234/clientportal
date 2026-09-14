<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\CifVisibility;
use PHPUnit\Framework\TestCase;

final class CifVisibilityTest extends TestCase
{
    public function testAlwaysVisibleWhenNoRules(): void
    {
        $this->assertTrue(CifVisibility::isVisible(['key' => 'x'], []));
    }

    public function testShowWhenScalarMatch(): void
    {
        $q = ['show_when' => ['marital_status' => 'Married']];
        $this->assertTrue(CifVisibility::isVisible($q, ['marital_status' => 'Married']));
        $this->assertFalse(CifVisibility::isVisible($q, ['marital_status' => 'Single']));
    }

    public function testShowWhenArrayExpected(): void
    {
        $q = ['show_when' => ['marital_status' => ['Married', 'Common-Law']]];
        $this->assertTrue(CifVisibility::isVisible($q, ['marital_status' => 'Common-Law']));
        $this->assertFalse(CifVisibility::isVisible($q, ['marital_status' => 'Single']));
    }

    public function testHiddenWhenOverrides(): void
    {
        $q = [
            'show_when' => ['flag' => 'Yes'],
            'hidden_when' => ['hide' => 'Yes'],
        ];
        $this->assertTrue(CifVisibility::isVisible($q, ['flag' => 'Yes', 'hide' => 'No']));
        $this->assertFalse(CifVisibility::isVisible($q, ['flag' => 'Yes', 'hide' => 'Yes']));
    }

    public function testShowAlsoWhenForms(): void
    {
        $q = [
            'show_also_when_forms' => ['waiver'],
            'show_when' => ['never' => 'match'],
        ];
        $this->assertTrue(CifVisibility::isVisible($q, [], ['waiver']));
        $this->assertFalse(CifVisibility::isVisible($q, [], ['criminal-rehab']));
    }

    public function testRulesMatchSkipsValueKey(): void
    {
        $this->assertTrue(CifVisibility::rulesMatch(
            ['value' => 'ignored', 'a' => '1'],
            ['a' => '1']
        ));
    }
}
