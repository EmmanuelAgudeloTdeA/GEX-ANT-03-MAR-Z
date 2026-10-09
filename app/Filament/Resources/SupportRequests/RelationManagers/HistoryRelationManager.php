<?php

namespace App\Filament\Resources\SupportRequests\RelationManagers;

use App\Models\SupportRequest;
use App\Support\Audit\AuditChangeMapper;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class HistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'auditLogs';

    protected static ?string $title = 'Historial';

    /**
     * HU11: el historial de auditoria es exclusivamente de lectura.
     */
    public function isReadOnly(): bool
    {
        return true;
    }

    /**
     * La pestaña solo se muestra a quienes pueden consultar el historial
     * de la solicitud.
     */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof SupportRequest
            && (auth()->user()?->can('viewHistory', $ownerRecord) ?? false);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('actor.code')
                    ->label('Actor')
                    ->formatStateUsing(
                        fn (?string $state, Model $record): string => $state
                            ? $state.' · '.$record->actor_role
                            : 'Sistema · '.$record->actor_role
                    ),

                TextColumn::make('event')
                    ->label('Evento')
                    ->badge(),

                TextColumn::make('field')
                    ->label('Campo')
                    ->formatStateUsing(
                        fn (?string $state): ?string => AuditChangeMapper::fieldLabel($state)
                    )
                    ->placeholder('—'),

                TextColumn::make('old_value')
                    ->label('Anterior')
                    ->formatStateUsing(
                        fn (?string $state, Model $record): ?string => AuditChangeMapper::value(
                            $record->field,
                            $state,
                        )
                    )
                    ->placeholder('—'),

                TextColumn::make('new_value')
                    ->label('Nuevo')
                    ->formatStateUsing(
                        fn (?string $state, Model $record): ?string => AuditChangeMapper::value(
                            $record->field,
                            $state,
                        )
                    )
                    ->placeholder('—'),

                TextColumn::make('reason')
                    ->label('Motivo')
                    ->wrap()
                    ->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(
                fn (Builder $query): Builder => $query->with('actor')
            )
            ->recordActions([])
            ->bulkActions([])
            ->headerActions([]);
    }
}
