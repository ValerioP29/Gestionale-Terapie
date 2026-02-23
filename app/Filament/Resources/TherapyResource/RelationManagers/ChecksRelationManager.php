<?php

namespace App\Filament\Resources\TherapyResource\RelationManagers;

use App\Models\TherapyFollowup;
use App\Services\Therapies\Followups\InitPeriodicCheckService;
use App\Services\Therapies\Followups\SaveFollowupAnswersService;
use Carbon\CarbonImmutable;
use Filament\Forms;
use Filament\Forms\Components\Field;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ChecksRelationManager extends RelationManager
{
    protected static string $relationship = 'checks';

    protected static ?string $title = 'Check periodici';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('occurred_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('occurred_at')->dateTime()->label('Avvenuto il'),
                Tables\Columns\TextColumn::make('check_type')->label('Tipologia')->badge()->formatStateUsing(fn (?string $state): string => $state === 'periodic' ? 'Periodico' : (string) $state),
                Tables\Columns\TextColumn::make('risk_score')->label('Indice di rischio'),
                Tables\Columns\TextColumn::make('follow_up_date')->label('Prossimo follow-up')->date(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('newPeriodicCheck')
                    ->label('Nuovo check periodico')
                    ->tooltip('Compila il check periodico per aggiornare andamento clinico e pianificazione.')
                    ->icon('heroicon-o-plus')
                    ->mountUsing(function (Forms\Form $form): void {
                        $today = CarbonImmutable::today();

                        /** @var TherapyFollowup|null $followup */
                        $followup = $this->ownerRecord->checks()
                            ->where('check_type', 'periodic')
                            ->whereDate('occurred_at', $today)
                            ->whereNull('canceled_at')
                            ->with('checklistAnswers')
                            ->first();

                        if ($followup === null) {
                            $form->fill([
                                'risk_score' => null,
                                'follow_up_date' => null,
                                'pharmacist_notes' => null,
                                'answers' => [],
                            ]);

                            return;
                        }

                        $state = [
                            'risk_score' => $followup->risk_score,
                            'follow_up_date' => optional($followup->follow_up_date)?->toDateString(),
                            'pharmacist_notes' => $followup->pharmacist_notes,
                            'answers' => [],
                        ];

                        foreach ($followup->checklistAnswers as $answer) {
                            $state['answers'][$answer->question_id] = $answer->answer_value;
                        }

                        $form->fill($state);
                    })
                    ->form(function (): array {
                        $fields = [
                            Forms\Components\TextInput::make('risk_score')->label('Indice di rischio (0-100)')->helperText('Valuta il rischio clinico percepito al momento del check.')->numeric()->minValue(0)->maxValue(100),
                            Forms\Components\DatePicker::make('follow_up_date')->label('Data prossimo follow-up')->helperText('Data suggerita per il controllo successivo.'),
                            Forms\Components\Textarea::make('pharmacist_notes')->label('Note del farmacista')->rows(4)->columnSpanFull(),
                        ];

                        $questions = $this->ownerRecord->checklistQuestions()
                            ->where('is_active', true)
                            ->get();

                        foreach ($questions as $question) {
                            $fields[] = $this->makeAnswerField($question->id, $question->label, $question->input_type, $question->options_json);
                        }

                        return $fields;
                    })
                    ->action(function (array $data): void {
                        $followup = app(InitPeriodicCheckService::class)->handle($this->ownerRecord);

                        app(SaveFollowupAnswersService::class)->handle($this->ownerRecord, $followup, $data);
                    }),
            ])
            ->actions([]);
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
