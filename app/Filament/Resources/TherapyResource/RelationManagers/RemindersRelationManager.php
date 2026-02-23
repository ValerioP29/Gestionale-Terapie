<?php

namespace App\Filament\Resources\TherapyResource\RelationManagers;

use App\Models\TherapyReminder;
use App\Services\Audit\AuditLogger;
use App\Services\Reminders\ReminderService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class RemindersRelationManager extends RelationManager
{
    protected static string $relationship = 'reminders';

    protected static ?string $title = 'Promemoria';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('title')->label('Titolo promemoria')->required()->maxLength(255),
            Forms\Components\Select::make('frequency')
                ->label('Frequenza')
                ->required()
                ->live()
                ->options([
                    'one_shot' => 'Una tantum',
                    'weekly' => 'Settimanale',
                    'biweekly' => 'Ogni 2 settimane',
                    'monthly' => 'Mensile',
                ]),
            Forms\Components\Select::make('weekday')
                ->label('Giorno della settimana')
                ->nullable()
                ->visible(fn (Forms\Get $get): bool => in_array($get('frequency'), ['weekly', 'biweekly'], true))
                ->required(fn (Forms\Get $get): bool => in_array($get('frequency'), ['weekly', 'biweekly'], true))
                ->options([
                    1 => 'Lunedì', 2 => 'Martedì', 3 => 'Mercoledì', 4 => 'Giovedì',
                    5 => 'Venerdì', 6 => 'Sabato', 7 => 'Domenica',
                ]),
            Forms\Components\DateTimePicker::make('first_due_at')->label('Prima scadenza')->helperText('Data/ora iniziale da cui calcolare le prossime scadenze.')->required(),
            Forms\Components\Select::make('status')
                ->required()
                ->default('active')
                ->label('Stato')
                ->options(['active' => 'Attivo', 'done' => 'Eseguito', 'paused' => 'In pausa', 'canceled' => 'Annullato (legacy)']),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('next_due_at')
            ->columns([
                Tables\Columns\TextColumn::make('title')->label('Promemoria')->searchable(),
                Tables\Columns\TextColumn::make('frequency')->label('Frequenza')->formatStateUsing(fn (?string $state): string => match ($state) { 'one_shot' => 'Una tantum', 'weekly' => 'Settimanale', 'biweekly' => 'Ogni 2 settimane', 'monthly' => 'Mensile', default => (string) $state, }),
                Tables\Columns\TextColumn::make('next_due_at')->label('Prossima scadenza')->dateTime(),
                Tables\Columns\TextColumn::make('last_done_at')->label('Ultima esecuzione')->dateTime()->placeholder('-'),
                Tables\Columns\TextColumn::make('status')->label('Stato')->badge()->formatStateUsing(fn (?string $state): string => match ($state) { 'active' => 'Attivo', 'done' => 'Eseguito', 'paused' => 'In pausa', 'canceled' => 'Annullato (legacy)', default => (string) $state, })->colors([
                    'success' => 'done',
                    'warning' => 'paused',
                    'primary' => 'active',
                ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Aggiungi promemoria')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['pharmacy_id'] = $this->ownerRecord->pharmacy_id;
                        $data['therapy_id'] = $this->ownerRecord->id;
                        $data['next_due_at'] = $data['next_due_at'] ?? $data['first_due_at'];

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('Modifica')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['next_due_at'] = $data['next_due_at'] ?? $data['first_due_at'];

                        return $data;
                    }),
                Tables\Actions\Action::make('mark_done')
                    ->label('Segna come eseguito')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn (TherapyReminder $record): bool => $record->status !== 'done')
                    ->action(function (TherapyReminder $record): void {
                        app(ReminderService::class)->markDone($record);

                        Notification::make()->success()->title('Promemoria aggiornato')->send();
                    }),
                Tables\Actions\Action::make('cancel_reminder')
                    ->label('Metti in pausa')
                    ->icon('heroicon-o-pause')
                    ->visible(fn (TherapyReminder $record): bool => $record->status === 'active')
                    ->action(function (TherapyReminder $record): void {
                        TherapyReminder::query()
                            ->whereKey($record->id)
                            ->where('pharmacy_id', $this->ownerRecord->pharmacy_id)
                            ->update(['status' => 'paused']);

                        app(AuditLogger::class)->log(
                            pharmacyId: $this->ownerRecord->pharmacy_id,
                            action: 'cancel_reminder',
                            subject: $record,
                            meta: [
                                'therapy_id' => $this->ownerRecord->id,
                            ],
                        );

                        Notification::make()->success()->title('Promemoria aggiornato')->send();
                    }),
            ]);
    }
}
