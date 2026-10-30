<?php

namespace Tests\Unit;

use App\Services\CycleCalendarService;
use App\Support\Money;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class MoneyAndCalendarTest extends TestCase
{
    public function test_multiplica_cuota_por_socios_sin_usar_float(): void
    {
        $this->assertSame('500000.00', Money::multiply('50000.00', 10));
        $this->assertSame('0.00', Money::of(0));
        $this->assertTrue(Money::isPositive('0.01'));
        $this->assertFalse(Money::isPositive('0'));
    }

    public function test_genera_fechas_semanales(): void
    {
        $service = new CycleCalendarService;
        $dates = $service->weeklyDates(Carbon::parse('2026-01-05'), 4);

        $this->assertSame([
            '2026-01-05',
            '2026-01-12',
            '2026-01-19',
            '2026-01-26',
        ], $dates);
    }
}
