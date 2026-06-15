<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BookingResource\Pages;
use App\Models\Booking;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BookingResource extends Resource
{
    protected static ?string $model = Booking::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'Pending bookings';

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $user = auth()->user();
        if (! $user) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        return parent::getEloquentQuery()
            ->whereIn('business_id', $user->businesses()->pluck('businesses.id'))
            ->orderByDesc('slot_start');
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('business.name')->label('Business')->searchable(),
            TextColumn::make('customer_phone_e164')->label('Customer'),
            TextColumn::make('service_type_key')->label('Service'),
            TextColumn::make('slot_start')->dateTime('M j, g:i a')->label('Start')->sortable(),
            TextColumn::make('address')->limit(40),
            TextColumn::make('status')
                ->badge()
                ->color(fn (string $state) => match ($state) {
                    Booking::PENDING => 'warning',
                    Booking::CONFIRMED => 'success',
                    Booking::CANCELED => 'danger',
                    default => 'gray',
                }),
        ])->filters([
            SelectFilter::make('status')->options([
                Booking::PENDING => 'Pending',
                Booking::CONFIRMED => 'Confirmed',
                Booking::CANCELED => 'Canceled',
                Booking::COMPLETED => 'Completed',
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBookings::route('/'),
            'view' => Pages\ViewBooking::route('/{record}'),
        ];
    }
}
