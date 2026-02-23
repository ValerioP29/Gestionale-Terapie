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

    protected static ?string $title = 'Follow-up manuali';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\DateTimePicker::make('occurred_at')->label('Data e ora attività')->helperText('Usa questa sezione per registrare un contatto manuale fuori dal check periodico.')->required()->default(now()),
            Forms\Components\TextInput::make('risk_score')->label('Indice di rischio (0-100)')->helperText('Valorizza solo se aggiornato durante il follow-up manuale.')->numeric()->minValue(0)->maxValue(100),
            Forms\Components\DatePicker::make('follow_up_date')->label('Data prossimo follow-up')->helperText('Inserisci la prossima data concordata con il paziente.'),
            Forms\Components\Textarea::make('pharmacist_notes')->label('Note del farmacista')->rows(4)->columnSpanFull(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('occurred_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('occurred_at')->dateTime()->label('Avvenuto il'),
                Tables\Columns\TextColumn::make('risk_score')->label('Indice di rischio'),
                Tables\Columns\TextColumn::make('follow_up_date')->label('Prossimo follow-up')->date(),
                Tables\Columns\TextColumn::make('canceled_at')->label('Annullato il')->dateTime()->toggleable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Nuovo follow-up manuale')
                    ->tooltip('Diverso dal check periodico: registra contatti/aggiornamenti manuali.')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['entry_type'] = 'followup';
                        $data['check_type'] = 'manual';
                        $data['pharmacy_id'] = $this->ownerRecord->pharmacy_id;

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('Modifica'),
            ]);
    }
}
