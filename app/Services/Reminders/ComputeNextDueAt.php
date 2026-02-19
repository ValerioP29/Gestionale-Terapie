<?php

namespace App\Services\Reminders;

use Carbon\CarbonImmutable;

class ComputeNextDueAt
{
    public function execute(CarbonImmutable $reference, string $frequency, ?int $weekday = null): ?CarbonImmutable
    {
        return match ($frequency) {
            'one_shot' => null,
            'weekly' => $this->alignWeekday($reference->addWeek(), $weekday),
            'biweekly' => $this->alignWeekday($reference->addWeeks(2), $weekday),
            'monthly' => $this->computeMonthly($reference, $weekday),
            default => null,
        };
    }

    private function alignWeekday(CarbonImmutable $date, ?int $weekday): CarbonImmutable
    {
        if ($weekday === null || $weekday < 1 || $weekday > 7) {
            return $date;
        }

        return $date->startOfWeek()->addDays($weekday - 1)->setTimeFrom($date);
    }

    private function computeMonthly(CarbonImmutable $reference, ?int $weekday): CarbonImmutable
    {
        $monthly = $reference->addMonthNoOverflow();

        if ($weekday === null || $weekday < 1 || $weekday > 7) {
            return $monthly;
        }

        $first = $monthly->startOfMonth()->setTimeFrom($reference);

        return $this->alignWeekday($first, $weekday);
    }
}
