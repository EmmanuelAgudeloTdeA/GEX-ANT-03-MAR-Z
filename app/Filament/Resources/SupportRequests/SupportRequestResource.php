<?php

namespace App\Filament\Resources\SupportRequests;

use App\Filament\Resources\SupportRequests\Pages\CreateSupportRequest;
use App\Filament\Resources\SupportRequests\Pages\ListSupportRequests;
use App\Filament\Resources\SupportRequests\Pages\ViewSupportRequest;
use App\Filament\Resources\SupportRequests\RelationManagers\CommentsRelationManager;
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

    // La restriccion por rol vive en la query base, no en un filtro que el
    // usuario pueda quitar (HU03, Tech Plan §25).
    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        return parent::getEloquentQuery()
            ->with(['category', 'requester', 'assignedAgent'])
            ->when(
                $user,
                fn (Builder $query) => $query->visibleTo($user),
                fn (Builder $query) => $query->whereRaw('1 = 0'),
            );
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

    /**
     * Los comentarios de avance viven dentro de su solicitud (HU06); el historial
     * de auditoria se agregara en HU11.
     *
     * @return array<class-string>
     */
    public static function getRelations(): array
    {
        return [
            CommentsRelationManager::class,
        ];
    }
}
