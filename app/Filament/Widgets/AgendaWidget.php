<?php

namespace App\Filament\Widgets;

use App\Models\TherapyReminder;
use App\Tenancy\CurrentPharmacy;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;

class AgendaWidget extends Widget
{
    protected static string $view = 'filament.widgets.agenda-widget';

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $tenantId = app(CurrentPharmacy::class)->getId();

        if ($tenantId === null) {
            return ['overdue' => collect(), 'today' => collect(), 'upcoming' => collect()];
        }

        $todayStart = Carbon::today();
        $todayEnd = Carbon::today()->endOfDay();
        $tomorrowStart = Carbon::tomorrow()->startOfDay();
        $upcomingEnd = Carbon::today()->addDays(7)->endOfDay();

        $baseQuery = TherapyReminder::query()
            ->where('pharmacy_id', $tenantId)
            ->where('status', 'active')
            ->orderByRaw('COALESCE(next_due_at, first_due_at) asc');

        return [
            'overdue' => (clone $baseQuery)->whereRaw('COALESCE(next_due_at, first_due_at) < ?', [$todayStart])->limit(10)->get(),
            'today' => (clone $baseQuery)->whereRaw('COALESCE(next_due_at, first_due_at) between ? and ?', [$todayStart, $todayEnd])->limit(10)->get(),
            'upcoming' => (clone $baseQuery)->whereRaw('COALESCE(next_due_at, first_due_at) between ? and ?', [$tomorrowStart, $upcomingEnd])->limit(10)->get(),
        ];
    }
}
