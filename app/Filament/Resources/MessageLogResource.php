<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MessageLogResource\Pages;
use App\Models\MessageLog;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MessageLogResource extends Resource
{
    protected static ?string $model = MessageLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationGroup = 'WhatsApp';
    protected static ?string $navigationLabel = 'Message Logs';

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('pharma_id')->disabled(),
                Forms\Components\TextInput::make('to')->disabled(),
                Forms\Components\Textarea::make('body')->disabled(),
                Forms\Components\TextInput::make('status')->disabled(),
                Forms\Components\Textarea::make('error')->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('pharma_id')->sortable(),
                Tables\Columns\TextColumn::make('to')->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sent_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'queued' => 'queued',
                        'sending' => 'sending',
                        'sent' => 'sent',
                        'failed' => 'failed',
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
}
