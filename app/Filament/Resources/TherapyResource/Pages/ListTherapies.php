<?php

namespace App\Filament\Resources\TherapyResource\Pages;

use App\Filament\Resources\TherapyResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTherapies extends ListRecords
{
    protected static string $resource = TherapyResource::class;

    public function getTitle(): string
    {
        return 'Terapie';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Nuova terapia')->modalHeading('Crea nuova terapia'),
        ];
    }
}
