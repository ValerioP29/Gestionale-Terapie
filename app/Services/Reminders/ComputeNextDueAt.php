<?php

namespace App\Services\Reminders;

use Carbon\CarbonImmutable;

class ComputeNextDueAt
{
    private const CLINICAL_TIMEZONE = 'Europe/Rome';

    public function execute(CarbonImmutable $reference, string $frequency, ?int $weekday = null): ?CarbonImmutable
    {
        $clinicalReference = $reference->setTimezone(self::CLINICAL_TIMEZONE);

        $clinicalNext = match ($frequency) {
            'one_shot' => null,
            'weekly' => $this->alignWeekday($clinicalReference->addWeek(), $weekday),
            'biweekly' => $this->alignWeekday($clinicalReference->addWeeks(2), $weekday),
            'monthly' => $this->computeMonthly($clinicalReference, $weekday),
            default => null,
        };

        return $clinicalNext?->setTimezone('UTC');
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
