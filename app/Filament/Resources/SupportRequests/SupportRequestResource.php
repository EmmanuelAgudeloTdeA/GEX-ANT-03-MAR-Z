<?php

namespace App\Filament\Resources\SupportRequests;

use App\Filament\Resources\SupportRequests\Pages\CreateSupportRequest;
use App\Filament\Resources\SupportRequests\Pages\ListSupportRequests;
use App\Filament\Resources\SupportRequests\Pages\ViewSupportRequest;
use App\Filament\Resources\SupportRequests\Schemas\SupportRequestForm;
use App\Filament\Resources\SupportRequests\Schemas\SupportRequestInfolist;
use App\Filament\Resources\SupportRequests\Tables\SupportRequestsTable;
use App\Models\SupportRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use UnitEnum;

class SupportRequestResource extends Resource
{
    protected static ?string $model = SupportRequest::class;

    protected static ?string $modelLabel = 'solicitud de soporte';

    protected static ?string $pluralModelLabel = 'solicitudes de soporte';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLifebuoy;

    protected static string|UnitEnum|null $navigationGroup = 'Soporte';

    public static function getTitleCaseModelLabel(): string
    {
        return Str::ucfirst(static::getModelLabel());
    }

    public static function getTitleCasePluralModelLabel(): string
    {
        return Str::ucfirst(static::getPluralModelLabel());
    }

    public static function form(Schema $schema): Schema
    {
        return SupportRequestForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SupportRequestInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SupportRequestsTable::configure($table);
    }

    // TODO HU03: aplicar el scope visibleTo(auth()->user()) para que cada rol vea solo lo suyo.
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('category');
    }

    // Sin pagina de edicion (PD-07): los cambios se hacen con acciones trazables.
    public static function getPages(): array
    {
        return [
            'index' => ListSupportRequests::route('/'),
            'create' => CreateSupportRequest::route('/create'),
            'view' => ViewSupportRequest::route('/{record}'),
        ];
    }
}
