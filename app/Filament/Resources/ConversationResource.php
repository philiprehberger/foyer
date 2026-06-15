<?php

namespace App\Filament\Resources;

use App\Models\Conversation;
use Filament\Resources\Resource;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ConversationResource extends Resource
{
    protected static ?string $model = Conversation::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $user = auth()->user();
        if (! $user) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        return parent::getEloquentQuery()
            ->whereIn('business_id', $user->businesses()->pluck('businesses.id'))
            ->orderByDesc('last_message_at');
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('business.name')->label('Business')->searchable(),
            TextColumn::make('customer_phone_e164')->label('Customer'),
            BadgeColumn::make('channel'),
            TextColumn::make('last_message_at')->dateTime('M j, g:i a')->sortable(),
            TextColumn::make('completed_at')->dateTime('M j, g:i a')->label('Completed'),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListConversations::route('/'),
        ];
    }
}
