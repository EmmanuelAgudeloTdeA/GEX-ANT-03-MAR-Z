<?php

namespace App\Filament\Resources\SupportRequests\Tables;

use App\Models\SupportRequest;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
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

                TextColumn::make('priority')
                    ->label('Prioridad')
                    ->badge()
                    ->sortable(query: function ($query, string $direction): void {
                        $direction = strtolower($direction) === 'desc'
                            ? 'desc'
                            : 'asc';

                        $query->orderByRaw(
                            "CASE priority
                                WHEN 'Alta' THEN 1
                                WHEN 'Media' THEN 2
                                WHEN 'Baja' THEN 3
                                ELSE 4
                            END {$direction}"
                        );
                    }),

                TextColumn::make('status')
                    ->label('Estado')
                    ->sortable()
                    ->badge(),

                TextColumn::make('updated_at')
                    ->label('Última actualización')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Fecha de creación')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('Propietario'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(fn () => SupportRequest::query()
                        ->distinct()
                        ->pluck('status', 'status')
                        ->toArray()),

                SelectFilter::make('priority')
                    ->label('Prioridad')
                    ->options([
                        'Alta' => 'Alta',
                        'Media' => 'Media',
                        'Baja' => 'Baja',
                    ]),

                SelectFilter::make('category')
                    ->label('Categoría')
                    ->options(fn () => SupportRequest::query()
                        ->distinct()
                        ->pluck('category', 'category')
                        ->toArray()),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Ver'),

                EditAction::make()
                    ->label('Priorizar')
                    ->visible(fn (): bool => auth()->user()?->isCoordinator() ?? false),
            ]);
    }
}