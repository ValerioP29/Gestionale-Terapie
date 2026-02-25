<?php

namespace App\Filament\Resources\TherapyResource\Pages;

use App\Filament\Resources\TherapyResource;
use App\Services\Therapies\CreateTherapyService;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreateTherapy extends CreateRecord
{
    protected static string $resource = TherapyResource::class;

    public function getTitle(): string
    {
        return 'Nuova terapia';
    }

    public function getBreadcrumb(): string
    {
        return 'Nuova terapia';
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('Salva');
    }

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        return app(CreateTherapyService::class)->handle($data);
    }
}
