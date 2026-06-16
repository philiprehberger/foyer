<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BusinessResource\Pages;
use App\Models\Business;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BusinessResource extends Resource
{
    protected static ?string $model = Business::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(120),
            TextInput::make('slug')->required()->maxLength(64),
            TextInput::make('timezone')->required()->default('America/Denver'),
            TimePicker::make('quiet_hours_start')->seconds(false)->default('21:00'),
            TimePicker::make('quiet_hours_end')->seconds(false)->default('08:00'),
            Select::make('persona')
                ->options(['professional' => 'Professional', 'casual' => 'Casual', 'gentle' => 'Gentle'])
                ->default('professional')
                ->required(),
            Textarea::make('system_prompt_suffix')
                ->maxLength(2000)
                ->helperText('Optional per-business voice tweak — appended to the agent prompt.'),
            KeyValue::make('service_area')->helperText('e.g. {"type": "zip_codes", "codes": ["80301","80302"]}'),
            KeyValue::make('business_hours')->helperText('Per-day hours.'),
            TextInput::make('min_lead_minutes')->numeric()->default(60),
            TextInput::make('max_lead_days')->numeric()->default(60),
            TextInput::make('human_handoff_phone')->tel()->maxLength(20),
            TextInput::make('human_handoff_threshold')->numeric()->default(0.6),
            TextInput::make('cost_ceiling_micros')->numeric()->default(500000)
                ->helperText('LLM cost ceiling, in micros (1,000,000 = $1.00). Per business per day.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable()->sortable(),
            TextColumn::make('slug')->searchable(),
            TextColumn::make('timezone'),
            IconColumn::make('kill_switch_at')
                ->label('Kill switch')
                ->boolean()
                ->trueIcon('heroicon-o-no-symbol')
                ->falseIcon('heroicon-o-check-circle'),
            TextColumn::make('created_at')->dateTime()->sortable(),
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        if (! $user) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        return parent::getEloquentQuery()->whereIn('id', $user->businesses()->pluck('businesses.id'));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBusinesses::route('/'),
            'create' => Pages\CreateBusiness::route('/create'),
            'edit' => Pages\EditBusiness::route('/{record}/edit'),
        ];
    }
}
