<?php

namespace Tests\Unit;

use App\Services\Reminders\ComputeNextDueAt;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class ComputeNextDueAtTest extends TestCase
{
    public function test_monthly_handles_end_of_month_without_overflow(): void
    {
        $service = new ComputeNextDueAt();

        $result = $service->execute(CarbonImmutable::parse('2026-01-31 09:00:00'), 'monthly');

        $this->assertSame('2026-02-28 09:00:00', $result?->format('Y-m-d H:i:s'));
    }

    public function test_weekly_aligns_to_requested_weekday(): void
    {
        $service = new ComputeNextDueAt();

        $result = $service->execute(CarbonImmutable::parse('2026-02-17 10:00:00'), 'weekly', 5);

        $this->assertSame('2026-02-27 10:00:00', $result?->format('Y-m-d H:i:s'));
    }
}
