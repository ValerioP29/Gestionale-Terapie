<?php

namespace App\Filament\Resources\TherapyResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ChecklistRelationManager extends RelationManager
{
    protected static string $relationship = 'checklistQuestions';

    protected static ?string $title = 'Checklist personalizzata';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('label')
                ->label('Domanda')
                ->helperText('Inserisci una domanda chiara per il farmacista.')
                ->required()
                ->maxLength(1000),
            Forms\Components\Select::make('input_type')
                ->options([
                    'boolean' => 'Sì/No',
                    'select' => 'Scelta multipla',
                    'text' => 'Testo libero',
                    'number' => 'Numero',
                    'date' => 'Data',
                ])
                ->label('Tipo risposta')
                ->helperText('Scegli il formato della risposta: dopo il salvataggio non è modificabile.')
                ->required()
                ->disabled(fn (string $operation): bool => $operation === 'edit')
                ->dehydrated(fn (string $operation): bool => $operation !== 'edit')
                ->live(),
            Forms\Components\TagsInput::make('options_json')
                ->label('Opzioni di risposta')
                ->helperText('Aggiungi una voce per ogni opzione (es. ottima, parziale, scarsa).')
                ->visible(fn (Forms\Get $get): bool => $get('input_type') === 'select')
                ->required(fn (Forms\Get $get): bool => $get('input_type') === 'select')
                ->dehydrateStateUsing(function ($state, Forms\Get $get): ?array {
                    if ($get('input_type') !== 'select') {
                        return null;
                    }

                    $values = collect((array) $state)
                        ->map(fn ($value): string => trim((string) $value))
                        ->filter(fn (string $value): bool => $value !== '')
                        ->values()
                        ->all();

                    return $values === [] ? null : $values;
                }),
            Forms\Components\Toggle::make('is_active')->label('Domanda attiva')->default(true),
            Forms\Components\TextInput::make('sort_order')->label('Ordine')->numeric()->default(0),
            Forms\Components\TextInput::make('question_key')
                ->label('Chiave tecnica (sola lettura)')
                ->helperText('Campo tecnico, utile solo per troubleshooting.')
                ->disabled()
                ->dehydrated(false)
                ->visible(fn (string $operation): bool => $operation === 'edit'),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')->label('Ordine')->sortable(),
                Tables\Columns\TextColumn::make('label')->label('Domanda')->searchable()->wrap(),
                Tables\Columns\TextColumn::make('input_type')->label('Tipo')->badge(),
                Tables\Columns\TextColumn::make('question_key')->label('Chiave tecnica')->copyable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\ToggleColumn::make('is_active')->label('Attiva'),
                Tables\Columns\IconColumn::make('is_custom')->label('Personalizzata')->boolean()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Aggiungi domanda')
                    ->mutateFormDataUsing(function (array $data): array {
                        $labelSlug = Str::slug((string) ($data['label'] ?? 'question'));
                        $data['question_key'] = sprintf('custom_%s_%04d', $labelSlug !== '' ? $labelSlug : 'question', random_int(0, 9999));
                        $data['is_custom'] = true;
                        $data['pharmacy_id'] = $this->ownerRecord->pharmacy_id;
                        $data['condition_key'] = (string) ($this->ownerRecord->currentChronicCare?->primary_condition ?? 'unspecified');

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('Modifica'),
            ]);
    }
}
