<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MessageLogResource\Pages;
use App\Models\MessageLog;
use App\Tenancy\CurrentPharmacy;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MessageLogResource extends Resource
{
    protected static ?string $model = MessageLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationGroup = 'WhatsApp';
    protected static ?string $navigationLabel = 'Log messaggi';

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('pharmacy_id')->label('Farmacia')->disabled(),
                Forms\Components\TextInput::make('to')->label('Destinatario')->disabled(),
                Forms\Components\Textarea::make('body')->label('Messaggio')->disabled(),
                Forms\Components\TextInput::make('status')->label('Stato')->disabled(),
                Forms\Components\Textarea::make('error')->label('Errore')->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('pharmacy_id')
                    ->label('Farmacia')
                    ->state(fn (MessageLog $record): string => (string) ($record->pharmacy_id ?? '-'))
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('pharma_id', $direction)),
                Tables\Columns\TextColumn::make('to')->label('Destinatario')->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Stato')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'queued' => 'In coda',
                        'sending' => 'In invio',
                        'sent' => 'Inviato',
                        'failed' => 'Fallito',
                        default => $state,
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('sent_at')
                    ->label('Inviato il')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creato il')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Stato')
                    ->options([
                        'queued' => 'In coda',
                        'sending' => 'In invio',
                        'sent' => 'Inviato',
                        'failed' => 'Fallito',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMessageLogs::route('/'),
            'view' => Pages\ViewMessageLog::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $tenantId = app(CurrentPharmacy::class)->getId();

        $query = parent::getEloquentQuery();

        if ($tenantId === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('pharma_id', $tenantId);
    }
}
