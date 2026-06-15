<?php

namespace App\Filament\Resources\BusinessResource\Pages;

use App\Filament\Resources\BusinessResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBusiness extends CreateRecord
{
    protected static string $resource = BusinessResource::class;

    protected function afterCreate(): void
    {
        $this->record->users()->syncWithoutDetaching([
            auth()->id() => ['role' => 'owner'],
        ]);
    }
}
