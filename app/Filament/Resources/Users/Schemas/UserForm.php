<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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

                Section::make('Acceso')
                    ->schema([
                        // Un solo rol de negocio por usuario (PD-18): simplifica
                        // las Policies y la separacion de funciones.
                        Select::make('roles')
                            ->label('Rol')
                            ->relationship('roles', 'name')
                            ->preload()
                            ->required(),

                        // Un usuario inactivo no entra al panel. Se desactiva en
                        // lugar de borrarlo para conservar la auditoria.
                        Toggle::make('is_active')
                            ->label('Activo')
                            ->default(true)
                            ->inline(false),

                        TextInput::make('code')
                            ->label('Código')
                            ->helperText('Se muestra en la auditoría en lugar del nombre.')
                            ->disabled()
                            ->dehydrated(false)
                            ->hiddenOn('create'),
                    ])
                    ->columns(3),
            ]);
    }
}
