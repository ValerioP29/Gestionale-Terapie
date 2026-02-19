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

    protected static ?string $title = 'Reminders';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('title')->required()->maxLength(255),
            Forms\Components\Select::make('frequency')
                ->required()
                ->live()
                ->options([
                    'one_shot' => 'One shot',
                    'weekly' => 'Weekly',
                    'biweekly' => 'Biweekly',
                    'monthly' => 'Monthly',
                ]),
            Forms\Components\Select::make('weekday')
                ->nullable()
                ->visible(fn (Forms\Get $get): bool => in_array($get('frequency'), ['weekly', 'biweekly'], true))
                ->required(fn (Forms\Get $get): bool => in_array($get('frequency'), ['weekly', 'biweekly'], true))
                ->options([
                    1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday',
                    5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday',
                ]),
            Forms\Components\DateTimePicker::make('first_due_at')->required(),
            Forms\Components\Select::make('status')
                ->required()
                ->default('active')
                ->options(['active' => 'Active', 'done' => 'Done', 'paused' => 'Paused']),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('next_due_at')
            ->columns([
                Tables\Columns\TextColumn::make('title')->searchable(),
                Tables\Columns\TextColumn::make('frequency'),
                Tables\Columns\TextColumn::make('next_due_at')->dateTime(),
                Tables\Columns\TextColumn::make('last_done_at')->dateTime()->placeholder('-'),
                Tables\Columns\TextColumn::make('status')->badge()->colors([
                    'success' => 'done',
                    'warning' => 'paused',
                    'primary' => 'active',
                ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['pharmacy_id'] = $this->ownerRecord->pharmacy_id;
                        $data['therapy_id'] = $this->ownerRecord->id;
                        $data['next_due_at'] = $data['next_due_at'] ?? $data['first_due_at'];

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['next_due_at'] = $data['next_due_at'] ?? $data['first_due_at'];

                        return $data;
                    }),
                Tables\Actions\Action::make('mark_done')
                    ->label('Mark done')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn (TherapyReminder $record): bool => $record->status !== 'done')
                    ->action(function (TherapyReminder $record): void {
                        app(ReminderService::class)->markDone($record);

                        Notification::make()->success()->title('Reminder aggiornato')->send();
                    }),
                Tables\Actions\Action::make('cancel_reminder')
                    ->label('Cancel')
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

                        Notification::make()->success()->title('Reminder annullato')->send();
                    }),
            ]);
    }
}
