<?php

namespace App\Filament\Resources\TherapyResource\Pages;

use App\Filament\Resources\TherapyResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewTherapy extends ViewRecord
{
    protected static string $resource = TherapyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
