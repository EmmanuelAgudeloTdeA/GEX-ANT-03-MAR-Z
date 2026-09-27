<?php

namespace App\Filament\Resources\Categories;

use App\Filament\Resources\Categories\Pages\ManageCategories;
use App\Models\Category;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Catalogo de categorias (HU02). Recurso simple con modales: las categorias
 * no se borran, se desactivan para no romper las solicitudes que las usan.
 */
class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static ?string $modelLabel = 'categoría';

    protected static ?string $pluralModelLabel = 'categorías';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = 'Administración';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(100)
                    ->unique(ignoreRecord: true),

                TextInput::make('description')
                    ->label('Descripción')
                    ->maxLength(255),

                Toggle::make('is_active')
                    ->label('Activa')
                    ->helperText('Las categorías inactivas no se ofrecen al crear solicitudes.')
                    ->default(true),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('description')
                    ->label('Descripción')
                    ->placeholder('—')
                    ->limit(60),

                IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean(),

                TextColumn::make('support_requests_count')
                    ->label('Solicitudes')
                    ->counts('supportRequests')
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Activa'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCategories::route('/'),
        ];
    }
}
