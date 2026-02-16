<?php

namespace App\Providers;

use App\Models\Followup;
use App\Models\Patient;
use App\Models\Reminder;
use App\Models\Report;
use App\Models\Therapy;
use App\Policies\FollowupPolicy;
use App\Policies\PatientPolicy;
use App\Policies\ReminderPolicy;
use App\Policies\ReportPolicy;
use App\Policies\TherapyPolicy;
use App\Tenancy\CurrentPharmacy;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CurrentPharmacy::class, fn () => new CurrentPharmacy());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Therapy::class, TherapyPolicy::class);
        Gate::policy(Patient::class, PatientPolicy::class);
        Gate::policy(Reminder::class, ReminderPolicy::class);
        Gate::policy(Followup::class, FollowupPolicy::class);
        Gate::policy(Report::class, ReportPolicy::class);
    }
}
