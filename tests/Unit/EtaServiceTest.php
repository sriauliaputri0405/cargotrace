<?php

namespace Tests\Unit;

use App\Services\EtaService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class EtaServiceTest extends TestCase
{
    private function eta(): EtaService
    {
        return new EtaService(['low_max' => 6, 'medium_max' => 24]);
    }

    private function expected(): Carbon
    {
        return Carbon::parse('2026-01-10 12:00:00');
    }

    public function test_no_estimate_means_not_delayed(): void
    {
        $result = $this->eta()->calculate($this->expected(), null);

        $this->assertFalse($result['delayed']);
        $this->assertNull($result['severity']);
    }

    public function test_estimate_equal_or_earlier_is_not_delayed(): void
    {
        $this->assertFalse($this->eta()->calculate($this->expected(), $this->expected()->copy())['delayed']);
        $this->assertFalse($this->eta()->calculate($this->expected(), $this->expected()->copy()->subHours(3))['delayed']);
    }

    public function test_up_to_six_hours_is_low(): void
    {
        $result = $this->eta()->calculate($this->expected(), $this->expected()->copy()->addHours(6));

        $this->assertTrue($result['delayed']);
        $this->assertSame(6, $result['hours']);
        $this->assertSame('LOW', $result['severity']);
    }

    public function test_just_over_six_hours_is_medium(): void
    {
        $result = $this->eta()->calculate($this->expected(), $this->expected()->copy()->addHours(6)->addMinute());

        $this->assertSame(7, $result['hours']);
        $this->assertSame('MEDIUM', $result['severity']);
    }

    public function test_exactly_twenty_four_hours_is_still_medium(): void
    {
        $result = $this->eta()->calculate($this->expected(), $this->expected()->copy()->addHours(24));

        $this->assertSame('MEDIUM', $result['severity']);
    }

    public function test_over_twenty_four_hours_is_high(): void
    {
        $result = $this->eta()->calculate($this->expected(), $this->expected()->copy()->addHours(24)->addMinute());

        $this->assertSame(25, $result['hours']);
        $this->assertSame('HIGH', $result['severity']);
    }
}