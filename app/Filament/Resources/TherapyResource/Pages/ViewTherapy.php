<?php

namespace App\Filament\Resources\TherapyResource\Pages;

use App\Filament\Resources\TherapyResource;
use App\Models\Therapy;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Throwable;

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
            Actions\Action::make('deleteTherapy')
                ->label('Elimina terapia')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->authorize(fn (Therapy $record): bool => auth()->user()?->can('delete', $record) ?? false)
                ->requiresConfirmation()
                ->modalHeading('Elimina terapia')
                ->modalDescription('Questa azione archivia la terapia (soft delete) e la rimuove dagli elenchi attivi.')
                ->modalSubmitActionLabel('Sì, elimina terapia')
                ->action(function (Therapy $record): void {
                    try {
                        $record->delete();

                        Notification::make()
                            ->success()
                            ->title('Terapia eliminata con successo')
                            ->send();

                        $this->redirect(TherapyResource::getUrl('index'));
                    } catch (Throwable $exception) {
                        report($exception);

                        Notification::make()
                            ->danger()
                            ->title('Impossibile eliminare la terapia')
                            ->body('Si è verificato un errore durante l\'eliminazione. Riprova più tardi.')
                            ->send();
                    }
                }),
        ];
    }

    public function hasRelationManagersAboveContent(): bool
    {
        return true;
    }
}
