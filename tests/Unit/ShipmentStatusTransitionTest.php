<?php

namespace Tests\Unit;

use App\Services\EtaService;
use App\Services\ShipmentService;
use PHPUnit\Framework\TestCase;

class ShipmentStatusTransitionTest extends TestCase
{
    private function service(): ShipmentService
    {
        return new ShipmentService(new EtaService(['low_max' => 6, 'medium_max' => 24]));
    }

    public function test_status_moves_forward_one_step_at_a_time(): void
    {
        $service = $this->service();

        $this->assertTrue($service->canTransition('CREATED', 'BOOKED'));
        $this->assertTrue($service->canTransition('BOOKED', 'PICKED_UP'));
        $this->assertTrue($service->canTransition('PICKED_UP', 'IN_TRANSIT'));
        $this->assertTrue($service->canTransition('IN_TRANSIT', 'DELIVERED'));
    }

    public function test_status_cannot_skip_a_step_or_go_back(): void
    {
        $service = $this->service();

        $this->assertFalse($service->canTransition('CREATED', 'DELIVERED'));
        $this->assertFalse($service->canTransition('BOOKED', 'IN_TRANSIT'));
        $this->assertFalse($service->canTransition('IN_TRANSIT', 'BOOKED'));
    }

    public function test_delivered_is_final(): void
    {
        $service = $this->service();

        $this->assertSame([], $service->allowedNext('DELIVERED'));
        $this->assertFalse($service->canTransition('DELIVERED', 'CREATED'));
    }
}