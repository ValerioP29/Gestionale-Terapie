<?php

namespace Tests\Unit;

use App\Services\Reminders\ComputeNextDueAt;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class ComputeNextDueAtTest extends TestCase
{
    public function test_monthly_handles_end_of_month_without_overflow_in_clinical_timezone(): void
    {
        $service = new ComputeNextDueAt();

        $referenceUtc = CarbonImmutable::parse('2026-01-31 08:00:00', 'UTC'); // 09:00 Europe/Rome
        $result = $service->execute($referenceUtc, 'monthly');

        $this->assertSame('2026-02-28 08:00:00', $result?->setTimezone('UTC')->format('Y-m-d H:i:s'));
        $this->assertSame('2026-02-28 09:00:00', $result?->setTimezone('Europe/Rome')->format('Y-m-d H:i:s'));
    }

    public function test_weekly_aligns_to_requested_weekday_using_rome_calendar(): void
    {
        $service = new ComputeNextDueAt();

        $referenceUtc = CarbonImmutable::parse('2026-02-17 09:00:00', 'UTC'); // 10:00 Europe/Rome
        $result = $service->execute($referenceUtc, 'weekly', 5);

        $this->assertSame('2026-02-27 09:00:00', $result?->setTimezone('UTC')->format('Y-m-d H:i:s'));
        $this->assertSame('2026-02-27 10:00:00', $result?->setTimezone('Europe/Rome')->format('Y-m-d H:i:s'));
    }

    public function test_weekly_near_midnight_keeps_next_day_in_rome(): void
    {
        $service = new ComputeNextDueAt();

        $referenceUtc = CarbonImmutable::parse('2026-03-28 23:30:00', 'UTC'); // 00:30 Europe/Rome (29/03)
        $result = $service->execute($referenceUtc, 'weekly');

        $this->assertSame('2026-04-05 00:30:00', $result?->setTimezone('Europe/Rome')->format('Y-m-d H:i:s'));
    }
}
