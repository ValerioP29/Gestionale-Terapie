<?php

namespace App\Filament\Resources\TherapyResource\Pages;

use App\Filament\Resources\TherapyResource;
use App\Services\Therapies\CreateTherapyService;
use Filament\Resources\Pages\CreateRecord;

class CreateTherapy extends CreateRecord
{
    protected static string $resource = TherapyResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        return app(CreateTherapyService::class)->handle($data);
    }
}
