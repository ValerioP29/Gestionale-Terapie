<?php

namespace App\Filament\Resources\TherapyResource\Pages;

use App\Filament\Resources\TherapyResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewTherapy extends ViewRecord
{
    protected static string $resource = TherapyResource::class;

    public function getTitle(): string
    {
        return 'Dettaglio terapia';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()->label('Modifica'),
        ];
    }
}
