<?php

namespace App\Filament\Resources\TherapyResource\Pages;

use App\Filament\Resources\TherapyResource;
use App\Models\Therapy;
use Filament\Resources\Pages\Page;

class ManageTherapyFollowups extends Page
{
    protected static string $resource = TherapyResource::class;

    protected static string $view = 'filament.resources.therapy-resource.pages.placeholder';

    public Therapy $record;

    public function mount(int|string $record): void
    {
        $this->record = TherapyResource::getEloquentQuery()->findOrFail($record);
    }
}
