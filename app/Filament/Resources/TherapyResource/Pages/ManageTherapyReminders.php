<?php

namespace App\Filament\Resources\TherapyResource\Pages;

use App\Filament\Resources\TherapyResource;
use Filament\Resources\Pages\Page;

class ManageTherapyReminders extends Page
{
    protected static string $resource = TherapyResource::class;

    protected static string $view = 'filament.resources.therapy-resource.pages.placeholder';

    protected static bool $shouldRegisterNavigation = false;

    public function mount(int|string $record): void
    {
        $therapy = TherapyResource::getEloquentQuery()->findOrFail($record);

        $this->redirect(TherapyResource::getUrl('view', ['record' => $therapy]));
    }
}
