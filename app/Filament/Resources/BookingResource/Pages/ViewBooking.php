<?php

namespace App\Filament\Resources\BookingResource\Pages;

use App\Filament\Resources\BookingResource;
use App\Jobs\SendOutboundSms;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\PhoneNumber;
use App\Models\SlotHold;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\DB;

class ViewBooking extends ViewRecord
{
    protected static string $resource = BookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('confirm')
                ->label('Confirm booking')
                ->color('success')
                ->icon('heroicon-o-check-circle')
                ->visible(fn () => $this->record->status === Booking::PENDING)
                ->requiresConfirmation()
                ->action(function () {
                    DB::transaction(function () {
                        $this->record->forceFill([
                            'status' => Booking::CONFIRMED,
                            'confirmed_by' => auth()->id(),
                            'confirmed_at' => CarbonImmutable::now(),
                        ])->save();

                        SlotHold::query()
                            ->where('conversation_id', $this->record->conversation_id)
                            ->where('status', SlotHold::ACTIVE)
                            ->update(['status' => SlotHold::CONFIRMED]);

                        AuditLog::create([
                            'actor_type' => 'user',
                            'actor_id' => (string) auth()->id(),
                            'business_id' => $this->record->business_id,
                            'event' => 'booking.confirmed.ui',
                            'payload' => ['booking_id' => $this->record->id],
                            'created_at' => CarbonImmutable::now(),
                        ]);
                    });

                    $this->notifyCustomer("Confirmed — see you {$this->record->slot_start->toDayDateTimeString()}.");
                }),

            Action::make('reject')
                ->label('Reject')
                ->color('danger')
                ->icon('heroicon-o-x-circle')
                ->visible(fn () => $this->record->status === Booking::PENDING)
                ->requiresConfirmation()
                ->action(function () {
                    DB::transaction(function () {
                        $this->record->forceFill(['status' => Booking::CANCELED])->save();
                        SlotHold::query()
                            ->where('conversation_id', $this->record->conversation_id)
                            ->where('status', SlotHold::ACTIVE)
                            ->update(['status' => SlotHold::RELEASED, 'released_at' => CarbonImmutable::now()]);
                    });
                    $this->notifyCustomer("We can't make that work — I can check alternatives if you'd like.");
                }),
        ];
    }

    private function notifyCustomer(string $body): void
    {
        if (! $this->record->customer_phone_e164) {
            return;
        }
        $twilio = PhoneNumber::query()->where('business_id', $this->record->business_id)->first();
        if (! $twilio) {
            return;
        }
        SendOutboundSms::dispatch(
            $this->record->conversation_id,
            $this->record->customer_phone_e164,
            $twilio->number_e164,
            $body,
        );
    }
}
