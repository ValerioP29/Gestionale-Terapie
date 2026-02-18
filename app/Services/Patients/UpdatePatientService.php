<?php

namespace App\Services\Patients;

use App\Models\Patient;
use App\Tenancy\CurrentPharmacy;
use Illuminate\Support\Arr;
use RuntimeException;

class UpdatePatientService
{
    public function handle(Patient $patient, array $payload): Patient
    {
        $tenantId = app(CurrentPharmacy::class)->getId();

        if ($tenantId === null) {
            throw new RuntimeException('Current pharmacy not resolved');
        }

        if ((int) $patient->pharmacy_id !== $tenantId) {
            throw (new \Illuminate\Database\Eloquent\ModelNotFoundException())->setModel(Patient::class, [$patient->id]);
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

        $patient->fill($data);
        $patient->save();

        return $patient->refresh();
    }
}
