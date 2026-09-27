<?php

namespace App\Filament\Resources\SupportRequests;

use App\Filament\Resources\SupportRequests\Pages\CreateSupportRequest;
use App\Filament\Resources\SupportRequests\Pages\EditSupportRequest;
use App\Filament\Resources\SupportRequests\Pages\ListSupportRequests;
use App\Filament\Resources\SupportRequests\Schemas\SupportRequestForm;
use App\Filament\Resources\SupportRequests\Tables\SupportRequestsTable;
use App\Models\SupportRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

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

    public static function table(Table $table): Table
    {
        return SupportRequestsTable::configure($table);
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
