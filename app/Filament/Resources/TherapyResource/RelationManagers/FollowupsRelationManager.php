<?php

namespace App\Filament\Resources\TherapyResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class FollowupsRelationManager extends RelationManager
{
    protected static string $relationship = 'manualFollowups';

    protected static ?string $title = 'Followups';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\DateTimePicker::make('occurred_at')->required()->default(now()),
            Forms\Components\TextInput::make('risk_score')->numeric()->minValue(0)->maxValue(100),
            Forms\Components\DatePicker::make('follow_up_date'),
            Forms\Components\Textarea::make('pharmacist_notes')->rows(4)->columnSpanFull(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('occurred_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('occurred_at')->dateTime()->label('Avvenuto il'),
                Tables\Columns\TextColumn::make('risk_score')->label('Rischio'),
                Tables\Columns\TextColumn::make('follow_up_date')->date(),
                Tables\Columns\TextColumn::make('canceled_at')->dateTime()->toggleable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Nuovo followup manuale')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['entry_type'] = 'followup';
                        $data['check_type'] = 'manual';
                        $data['pharmacy_id'] = $this->ownerRecord->pharmacy_id;

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }
}
