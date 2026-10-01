<?php

namespace App\Filament\Resources\AuditLogs;

use App\Enums\AuditEvent;
use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Filament\Resources\AuditLogs\Pages\ViewAuditLog;
use App\Models\AuditLog;
use App\Models\User;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static ?string $modelLabel = 'registro de auditoría';

    protected static ?string $pluralModelLabel = 'historial de auditoría';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = 'Soporte';

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user?->hasRole(['auditor', 'super_admin']) ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['actor', 'supportRequest']);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información del registro')
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Fecha')
                            ->dateTime(),

                        TextEntry::make('support_request_id')
                            ->label('Solicitud'),

                        TextEntry::make('actor.code')
                            ->label('Actor')
                            ->formatStateUsing(
                                fn (?string $state, AuditLog $record): string => $state
                                    ? $state.' · '.$record->actor_role
                                    : 'Sistema · '.$record->actor_role
                            ),

                        TextEntry::make('actor_role')
                            ->label('Rol del actor'),

                        TextEntry::make('event')
                            ->label('Evento')
                            ->badge()
                            ->formatStateUsing(
                                fn (AuditEvent|string|null $state): string => $state instanceof AuditEvent
                                    ? $state->getLabel()
                                    : (AuditEvent::tryFrom((string) $state)?->getLabel() ?? (string) $state)
                            ),

                        TextEntry::make('field')
                            ->label('Campo')
                            ->placeholder('—'),
                    ]),

                Section::make('Cambio registrado')
                    ->schema([
                        TextEntry::make('old_value')
                            ->label('Valor anterior')
                            ->formatStateUsing(
                                fn (?string $state, AuditLog $record): ?string => self::formatValue(
                                    $record->field,
                                    $state
                                )
                            )
                            ->placeholder('—'),

                        TextEntry::make('new_value')
                            ->label('Valor nuevo')
                            ->formatStateUsing(
                                fn (?string $state, AuditLog $record): ?string => self::formatValue(
                                    $record->field,
                                    $state
                                )
                            )
                            ->placeholder('—'),

                        TextEntry::make('reason')
                            ->label('Motivo')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('support_request_id')
                    ->label('Solicitud')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('actor.code')
                    ->label('Actor')
                    ->formatStateUsing(
                        fn (?string $state, AuditLog $record): string => $state
                            ? $state.' · '.$record->actor_role
                            : 'Sistema · '.$record->actor_role
                    )
                    ->searchable(),

                Tables\Columns\TextColumn::make('event')
                    ->label('Evento')
                    ->badge()
                    ->formatStateUsing(
                        fn (AuditEvent|string|null $state): string => $state instanceof AuditEvent
                            ? $state->getLabel()
                            : (AuditEvent::tryFrom((string) $state)?->getLabel() ?? (string) $state)
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('field')
                    ->label('Campo')
                    ->placeholder('—')
                    ->searchable(),

                Tables\Columns\TextColumn::make('old_value')
                    ->label('Anterior')
                    ->formatStateUsing(
                        fn (?string $state, AuditLog $record): ?string => self::formatValue(
                            $record->field,
                            $state
                        )
                    )
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('new_value')
                    ->label('Nuevo')
                    ->formatStateUsing(
                        fn (?string $state, AuditLog $record): ?string => self::formatValue(
                            $record->field,
                            $state
                        )
                    )
                    ->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\Filter::make('support_request_id')
                    ->label('Solicitud')
                    ->form([
                        TextInput::make('support_request_id')
                            ->label('ID de solicitud')
                            ->numeric(),
                    ])
                    ->query(
                        fn (Builder $query, array $data): Builder => $query->when(
                            $data['support_request_id'] ?? null,
                            fn (Builder $query, $value): Builder => $query->where(
                                'support_request_id',
                                $value
                            )
                        )
                    ),

                Tables\Filters\SelectFilter::make('event')
                    ->label('Evento')
                    ->options(
                        collect(AuditEvent::cases())
                            ->mapWithKeys(
                                fn (AuditEvent $event): array => [
                                    $event->value => $event->getLabel(),
                                ]
                            )
                            ->all()
                    ),

                Tables\Filters\SelectFilter::make('actor_role')
                    ->label('Rol del actor')
                    ->options([
                        'solicitante' => 'Solicitante',
                        'agente' => 'Agente',
                        'coordinador' => 'Coordinador',
                        'auditor' => 'Auditor',
                        'super_admin' => 'Super admin',
                        'system' => 'Sistema',
                    ]),

                Tables\Filters\Filter::make('field')
                    ->label('Campo')
                    ->form([
                        TextInput::make('field')
                            ->label('Nombre del campo'),
                    ])
                    ->query(
                        fn (Builder $query, array $data): Builder => $query->when(
                            $data['field'] ?? null,
                            fn (Builder $query, $value): Builder => $query->where(
                                'field',
                                'like',
                                '%'.$value.'%'
                            )
                        )
                    ),

                Tables\Filters\Filter::make('created_at')
                    ->label('Fecha')
                    ->form([
                        DatePicker::make('from')
                            ->label('Desde'),

                        DatePicker::make('until')
                            ->label('Hasta'),
                    ])
                    ->query(
                        fn (Builder $query, array $data): Builder => $query
                            ->when(
                                $data['from'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate(
                                    'created_at',
                                    '>=',
                                    $date
                                )
                            )
                            ->when(
                                $data['until'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate(
                                    'created_at',
                                    '<=',
                                    $date
                                )
                            )
                    ),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Ver'),
            ])
            ->toolbarActions([]);
    }

    // Solo lista y detalle: la auditoria no se crea ni se edita desde el panel.
    public static function getPages(): array
    {
        return [
            'index' => ListAuditLogs::route('/'),
            'view' => ViewAuditLog::route('/{record}'),
        ];
    }

    private static function formatValue(?string $field, ?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($field) {
            'priority' => self::formatPriority($value),
            'status' => self::formatStatus($value),
            'assigned_agent_id' => self::formatAssignedAgent($value),
            default => $value,
        };
    }

    private static function formatPriority(string $value): string
    {
        $priority = RequestPriority::tryFrom((int) $value);

        return $priority?->getLabel() ?? $value;
    }

    private static function formatStatus(string $value): string
    {
        $status = RequestStatus::tryFrom($value);

        return $status?->getLabel() ?? $value;
    }

    private static function formatAssignedAgent(string $value): string
    {
        $agent = User::query()->find($value);

        return $agent?->code ?? $value;
    }
}
