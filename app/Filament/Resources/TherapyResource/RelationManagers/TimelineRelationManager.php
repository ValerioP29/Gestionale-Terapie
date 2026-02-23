<?php

namespace App\Filament\Resources\TherapyResource\RelationManagers;

use App\Models\TherapyChecklistAnswer;
use App\Models\TherapyFollowup;
use App\Services\Therapies\Followups\CancelFollowupService;
use App\Services\Therapies\Followups\SaveFollowupAnswersService;
use Filament\Forms;
use Filament\Forms\Components\Field;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class TimelineRelationManager extends RelationManager
{
    protected static string $relationship = 'followups';

    protected static ?string $title = 'Timeline check e follow-up';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\DateTimePicker::make('occurred_at')
                ->label('Data e ora esecuzione')
                ->required(),
            Forms\Components\TextInput::make('risk_score')
                ->label('Indice di rischio (0-100)')
                ->numeric()
                ->minValue(0)
                ->maxValue(100),
            Forms\Components\DatePicker::make('follow_up_date')
                ->label('Data pianificata prossimo follow-up'),
            Forms\Components\Textarea::make('pharmacist_notes')
                ->label('Note sintetiche')
                ->rows(4)
                ->columnSpanFull(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('occurred_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('entry_type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'check' ? 'Check periodico' : 'Follow-up manuale'),
                Tables\Columns\TextColumn::make('occurred_at')
                    ->label('Data esecuzione')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('follow_up_date')
                    ->label('Data pianificata')
                    ->date('d/m/Y')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('status_readable')
                    ->label('Stato')
                    ->badge()
                    ->state(fn (TherapyFollowup $record): string => $this->statusLabel($record))
                    ->colors([
                        'danger' => fn (string $state): bool => $state === 'Annullato',
                        'success' => fn (string $state): bool => $state === 'Eseguito',
                        'warning' => fn (string $state): bool => $state === 'Pianificato',
                    ]),
                Tables\Columns\TextColumn::make('risk_score')
                    ->label('Rischio clinico')
                    ->formatStateUsing(fn ($state): string => $state === null ? '-' : "{$state}/100"),
                Tables\Columns\TextColumn::make('pharmacist_notes')
                    ->label('Note sintetiche')
                    ->limit(80)
                    ->placeholder('Nessuna nota')
                    ->wrap(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Apri dettaglio'),
                Tables\Actions\Action::make('compile_check')
                    ->label('Compila check')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->visible(fn (TherapyFollowup $record): bool => $record->entry_type === 'check' && $record->canceled_at === null)
                    ->mountUsing(function (Forms\Form $form, TherapyFollowup $record): void {
                        $record->loadMissing('checklistAnswers');

                        $state = [
                            'risk_score' => $record->risk_score,
                            'follow_up_date' => optional($record->follow_up_date)?->toDateString(),
                            'pharmacist_notes' => $record->pharmacist_notes,
                            'answers' => [],
                        ];

                        foreach ($record->checklistAnswers as $answer) {
                            $state['answers'][$answer->question_id] = $answer->answer_value;
                        }

                        $form->fill($state);
                    })
                    ->form(function (): array {
                        $fields = [
                            Forms\Components\TextInput::make('risk_score')
                                ->label('Indice di rischio (0-100)')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(100)
                                ->helperText('Aggiorna il rischio al termine del check periodico.'),
                            Forms\Components\DatePicker::make('follow_up_date')
                                ->label('Data prossimo follow-up')
                                ->helperText('Data pianificata per il controllo successivo.'),
                            Forms\Components\Textarea::make('pharmacist_notes')
                                ->label('Note del farmacista')
                                ->rows(4)
                                ->columnSpanFull(),
                        ];

                        $questions = $this->ownerRecord->checklistQuestions()
                            ->where('is_active', true)
                            ->get();

                        foreach ($questions as $question) {
                            $fields[] = $this->makeAnswerField($question->id, $question->label, $question->input_type, $question->options_json);
                        }

                        return $fields;
                    })
                    ->action(function (array $data, TherapyFollowup $record): void {
                        app(SaveFollowupAnswersService::class)->handle($this->ownerRecord, $record, $data);

                        Notification::make()
                            ->success()
                            ->title('Check periodico aggiornato')
                            ->send();
                    }),
                Tables\Actions\EditAction::make()
                    ->label('Modifica follow-up')
                    ->visible(fn (TherapyFollowup $record): bool => $record->entry_type === 'followup' && $record->canceled_at === null),
                Tables\Actions\Action::make('cancel')
                    ->label('Annulla')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (TherapyFollowup $record): bool => $record->canceled_at === null)
                    ->action(function (TherapyFollowup $record): void {
                        app(CancelFollowupService::class)->handle($this->ownerRecord, $record);

                        Notification::make()
                            ->success()
                            ->title('Evento annullato')
                            ->send();
                    }),
            ])
            ->emptyStateHeading('Nessun evento in timeline')
            ->emptyStateDescription('Qui vedi in ordine cronologico check periodici e follow-up manuali.');
    }

    private function statusLabel(TherapyFollowup $followup): string
    {
        if ($followup->canceled_at !== null) {
            return 'Annullato';
        }

        if ($followup->follow_up_date !== null && $followup->follow_up_date->isFuture()) {
            return 'Pianificato';
        }

        return 'Eseguito';
    }

    /**
     * @param array<int, string>|null $options
     */
    private function makeAnswerField(int $questionId, string $label, string $inputType, ?array $options): Field
    {
        $name = "answers.{$questionId}";

        return match ($inputType) {
            'boolean' => Forms\Components\Toggle::make($name)->label($label)->columnSpanFull(),
            'select' => Forms\Components\Select::make($name)
                ->label($label)
                ->options($this->normalizeOptions($options))
                ->columnSpanFull(),
            'number' => Forms\Components\TextInput::make($name)->label($label)->numeric()->columnSpanFull(),
            'date' => Forms\Components\DatePicker::make($name)->label($label)->columnSpanFull(),
            default => Forms\Components\Textarea::make($name)->label($label)->columnSpanFull(),
        };
    }

    /**
     * @param array<int, string>|null $options
     * @return array<string, string>
     */
    private function normalizeOptions(?array $options): array
    {
        return collect($options ?? [])
            ->map(fn (mixed $option): string => (string) $option)
            ->filter(fn (string $option): bool => $option !== '')
            ->mapWithKeys(fn (string $option): array => [$option => $option])
            ->all();
    }
}
