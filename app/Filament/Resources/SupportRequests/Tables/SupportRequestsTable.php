<?php

namespace App\Filament\Resources\SupportRequests\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SupportRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->formatStateUsing(fn (int $state): string => "#{$state}")
                    ->searchable()
                    ->sortable(),

                TextColumn::make('title')
                    ->label('Título')
                    ->limit(60)
                    ->tooltip(fn (TextColumn $column): ?string => strlen((string) $column->getState()) > 60 ? $column->getState() : null)
                    ->searchable(),

                TextColumn::make('category.name')
                    ->label('Categoría')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Creada')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label('Última actualización')
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
