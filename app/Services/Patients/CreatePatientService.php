<?php

namespace App\Services\Patients;

use App\Models\Patient;
use App\Tenancy\CurrentPharmacy;
use Illuminate\Support\Arr;
use RuntimeException;

class CreatePatientService
{
    public function handle(array $payload): Patient
    {
        $tenantId = app(CurrentPharmacy::class)->getId();

        if ($tenantId === null) {
            throw new RuntimeException('Current pharmacy not resolved');
        }

        $data = Arr::only($payload, [
            'first_name',
            'last_name',
            'birth_date',
            'codice_fiscale',
            'gender',
            'phone',
            'email',
            'notes',
        ]);

        $data['pharmacy_id'] = $tenantId;

        return Patient::query()->create($data);
    }
}
