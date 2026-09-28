<?php

namespace App\Filament\Resources\SupportRequests\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SupportRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('Título')
                    ->required()
                    ->disabledOn('edit'),

                Textarea::make('description')
                    ->label('Descripción')
                    ->required()
                    ->disabledOn('edit'),

                TextInput::make('category')
                    ->label('Categoría')
                    ->required()
                    ->disabledOn('edit'),

                Select::make('priority')
                    ->label('Prioridad')
                    ->options([
                        'Baja' => 'Baja',
                        'Media' => 'Media',
                        'Alta' => 'Alta',
                    ])
                    ->default('Media')
                    ->required()
                    ->hiddenOn('create'),
            ]);
    }
}