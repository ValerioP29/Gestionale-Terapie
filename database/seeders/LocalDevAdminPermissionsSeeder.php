<?php

namespace Database\Seeders;

use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Database\Seeder;

class LocalDevAdminPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->isLocal()) {
            return;
        }

        $adminEmails = collect(explode(',', (string) env('LOCAL_ADMIN_EMAILS', 'test@example.com')))
            ->map(fn (string $email): string => trim($email))
            ->filter();

        if ($adminEmails->isEmpty()) {
            return;
        }

        $pharmacyId = Pharmacy::query()->value('id');

        if ($pharmacyId === null) {
            return;
        }

        User::query()
            ->whereIn('email', $adminEmails->all())
            ->update(['pharmacy_id' => $pharmacyId]);
    }
}
