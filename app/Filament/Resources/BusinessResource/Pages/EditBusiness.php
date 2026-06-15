<?php

namespace App\Filament\Resources\BusinessResource\Pages;

use App\Filament\Resources\BusinessResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditBusiness extends EditRecord
{
    protected static string $resource = BusinessResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('toggleKillSwitch')
                ->label(fn () => $this->record->kill_switch_at ? 'Disable kill switch' : 'Enable kill switch')
                ->color(fn () => $this->record->kill_switch_at ? 'success' : 'danger')
                ->icon('heroicon-o-no-symbol')
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->kill_switch_at = $this->record->kill_switch_at ? null : now();
                    $this->record->save();
                }),
        ];
    }
}
