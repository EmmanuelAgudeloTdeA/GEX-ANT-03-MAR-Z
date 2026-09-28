<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información del usuario')
                    ->schema([
                        TextEntry::make('name')
                            ->label('Nombre'),

                        TextEntry::make('email')
                            ->label('Correo')
                            ->copyable(),

                        TextEntry::make('created_at')
                            ->label('Creado')
                            ->dateTime(),

                        TextEntry::make('updated_at')
                            ->label('Actualizado')
                            ->dateTime(),
                    ])
                    ->columns(2),

                Section::make('Acceso')
                    ->schema([
                        TextEntry::make('roles.name')
                            ->label('Rol')
                            ->badge()
                            ->placeholder('Sin rol asignado'),

                        IconEntry::make('is_active')
                            ->label('Activo')
                            ->boolean(),

                        TextEntry::make('code')
                            ->label('Código'),
                    ])
                    ->columns(3),
            ]);
    }
}
