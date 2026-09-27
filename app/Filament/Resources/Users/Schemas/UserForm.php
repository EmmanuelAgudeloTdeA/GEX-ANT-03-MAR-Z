<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos de la cuenta')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->label('Correo')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),

                        // El modelo User ya tiene el cast 'password' => 'hashed',
                        // por eso aqui no se hace Hash::make() manualmente.
                        TextInput::make('password')
                            ->label('Contraseña')
                            ->password()
                            ->revealable()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->maxLength(255)
                            ->helperText('Obligatoria al crear. Déjala vacía si no quieres cambiarla.'),
                    ])
                    ->columns(2),

                Section::make('Permisos de acceso')
                    ->schema([
                        // Igual que la contraseña: obligatorio al crear, pero al
                        // editar se puede dejar vacío para quitar todos los roles.
                        Select::make('roles')
                            ->label('Roles')
                            ->relationship('roles', 'name')
                            ->multiple()
                            ->preload()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->helperText('Obligatorio al crear. Al editar puedes dejarlo vacío para quitarle todos los roles.'),
                    ]),
            ]);
    }
}
