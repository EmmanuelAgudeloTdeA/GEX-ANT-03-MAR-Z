<?php

namespace App\Filament\Resources\SupportRequests\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SupportRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Título')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category')
                    ->label('Categoría')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Estado'),
                TextColumn::make('created_at')
                    ->label('Fecha de creación')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Propietario'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
