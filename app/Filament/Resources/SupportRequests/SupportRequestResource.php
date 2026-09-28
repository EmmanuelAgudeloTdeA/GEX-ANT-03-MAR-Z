<?php

namespace App\Filament\Resources\SupportRequests;

use App\Filament\Resources\SupportRequests\Pages\CreateSupportRequest;
use App\Filament\Resources\SupportRequests\Pages\EditSupportRequest;
use App\Filament\Resources\SupportRequests\Pages\ListSupportRequests;
use App\Filament\Resources\SupportRequests\Schemas\SupportRequestForm;
use App\Filament\Resources\SupportRequests\Tables\SupportRequestsTable;
use App\Models\SupportRequest;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SupportRequestResource extends Resource
{
    protected static ?string $model = SupportRequest::class;

    protected static ?string $modelLabel = 'solicitud de soporte';

    protected static ?string $pluralModelLabel = 'solicitudes de soporte';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return SupportRequestForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('id')
                    ->label('ID'),

                TextEntry::make('title')
                    ->label('Título'),

                TextEntry::make('description')
                    ->label('Descripción'),

                TextEntry::make('category')
                    ->label('Categoría'),

                TextEntry::make('priority')
                    ->label('Prioridad')
                    ->badge(),

                TextEntry::make('status')
                    ->label('Estado')
                    ->badge(),

                TextEntry::make('user.name')
                    ->label('Propietario'),

                TextEntry::make('created_at')
                    ->label('Fecha de creación')
                    ->dateTime(),

                TextEntry::make('updated_at')
                    ->label('Última actualización')
                    ->dateTime(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return SupportRequestsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = auth()->user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isCoordinator()) {
            return $query;
        }

        return $query->where('user_id', $user->id);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSupportRequests::route('/'),
            'create' => CreateSupportRequest::route('/create'),
            'edit' => EditSupportRequest::route('/{record}/edit'),
        ];
    }
}