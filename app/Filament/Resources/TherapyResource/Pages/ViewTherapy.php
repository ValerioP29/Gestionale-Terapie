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
            Actions\Action::make('followups')->label('Followups')->url(fn (): string => TherapyResource::getUrl('followups', ['record' => $this->record])),
            Actions\Action::make('reminders')->label('Reminders')->url(fn (): string => TherapyResource::getUrl('reminders', ['record' => $this->record])),
            Actions\Action::make('reports')->label('Reports')->url(fn (): string => TherapyResource::getUrl('reports', ['record' => $this->record])),
        ];
    }
}
