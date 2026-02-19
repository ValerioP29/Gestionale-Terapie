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

    protected static ?string $title = 'Checklist';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('label')
                ->required()
                ->maxLength(1000),
            Forms\Components\Select::make('input_type')
                ->options([
                    'boolean' => 'Boolean',
                    'select' => 'Select',
                    'text' => 'Text',
                    'number' => 'Number',
                    'date' => 'Date',
                ])
                ->required()
                ->disabled(fn (string $operation): bool => $operation === 'edit')
                ->dehydrated(fn (string $operation): bool => $operation !== 'edit')
                ->live(),
            Forms\Components\TagsInput::make('options_json')
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
            Forms\Components\Toggle::make('is_active')->default(true),
            Forms\Components\TextInput::make('sort_order')->numeric()->default(0),
            Forms\Components\TextInput::make('question_key')
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
                Tables\Columns\TextColumn::make('sort_order')->sortable(),
                Tables\Columns\TextColumn::make('label')->searchable()->wrap(),
                Tables\Columns\TextColumn::make('input_type')->badge(),
                Tables\Columns\TextColumn::make('question_key')->copyable(),
                Tables\Columns\ToggleColumn::make('is_active'),
                Tables\Columns\IconColumn::make('is_custom')->boolean(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
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
                Tables\Actions\EditAction::make(),
            ]);
    }
}
