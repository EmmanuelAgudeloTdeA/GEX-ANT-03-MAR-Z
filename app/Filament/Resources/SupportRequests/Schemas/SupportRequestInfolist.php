<?php

namespace App\Filament\Resources\SupportRequests\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SupportRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Solicitud')
                    ->schema([
                        TextEntry::make('id')
                            ->label('ID')
                            ->formatStateUsing(fn (int $state): string => "#{$state}"),

                        TextEntry::make('status')
                            ->label('Estado')
                            ->badge(),

                        TextEntry::make('priority')
                            ->label('Prioridad')
                            ->badge()
                            ->placeholder('Sin priorizar'),

                        TextEntry::make('title')
                            ->label('Título')
                            ->columnSpanFull(),

                        TextEntry::make('category.name')
                            ->label('Categoría')
                            ->badge()
                            ->color('gray'),

                        TextEntry::make('description')
                            ->label('Descripción')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpan(2),

                Section::make('Seguimiento')
                    ->schema([
                        TextEntry::make('requester.name')
                            ->label('Solicitante'),

                        TextEntry::make('created_at')
                            ->label('Creada')
                            ->dateTime(),

                        TextEntry::make('updated_at')
                            ->label('Última actualización')
                            ->since()
                            ->dateTimeTooltip(),

                        TextEntry::make('assignedAgent.name')
                            ->label('Agente asignado')
                            ->placeholder('Sin asignar'),

                        // Quien asigno y cuando (HU05) solo lo necesita coordinacion.
                        TextEntry::make('assignedBy.name')
                            ->label('Asignada por')
                            ->placeholder('—')
                            ->visible(fn (): bool => auth()->user()?->can('Assign:SupportRequest') ?? false),

                        TextEntry::make('assigned_at')
                            ->label('Asignada el')
                            ->dateTime()
                            ->placeholder('—')
                            ->visible(fn (): bool => auth()->user()?->can('Assign:SupportRequest') ?? false),
                    ])
                    ->columnSpan(1),
            ]);
    }
}
