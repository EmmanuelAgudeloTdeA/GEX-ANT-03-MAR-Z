<?php

namespace App\Filament\Resources\SupportRequests\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class SupportRequestForm
{
    /**
     * Solo pide lo que escribe el solicitante. ID, fecha, estado y propietario
     * los fija el servicio CreateSupportRequest (HU02).
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos de la solicitud')
                    ->schema([
                        TextInput::make('title')
                            ->label('Título')
                            ->required()
                            ->maxLength(150),

                        Select::make('category_id')
                            ->label('Categoría')
                            ->relationship('category', 'name', fn (Builder $query) => $query->active())
                            ->searchable()
                            ->preload()
                            ->required(),

                        Textarea::make('description')
                            ->label('Descripción')
                            ->required()
                            ->maxLength(5000)
                            ->autosize()
                            ->rows(5),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
