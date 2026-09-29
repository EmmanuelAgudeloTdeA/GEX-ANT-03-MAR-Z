<?php

namespace App\Filament\Resources\SupportRequests\Tables;

use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use App\Filament\Actions\AssignAction;
use App\Filament\Actions\PrioritizeAction;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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

                // Se ordena por la posicion en el flujo (Nuevo -> Cerrada), no alfabeticamente.
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderByRaw(
                        self::statusFlowOrder().' '.($direction === 'desc' ? 'desc' : 'asc')
                    )),

                TextColumn::make('priority')
                    ->label('Prioridad')
                    ->badge()
                    ->placeholder('Sin priorizar')
                    ->sortable(),

                // No es ordenable a proposito: ordenar por persona es el primer paso hacia un ranking (BR-17).
                TextColumn::make('assignedAgent.name')
                    ->label('Agente asignado')
                    ->placeholder('Sin asignar')
                    ->visible(fn (): bool => auth()->user()?->can('Assign:SupportRequest') ?? false),

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
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(RequestStatus::class)
                    ->multiple(),

                SelectFilter::make('priority')
                    ->label('Prioridad')
                    ->options(['none' => 'Sin priorizar'] + collect(RequestPriority::cases())
                        ->mapWithKeys(fn (RequestPriority $priority): array => [$priority->value => $priority->getLabel()])
                        ->all())
                    ->multiple()
                    ->query(function (Builder $query, array $data): Builder {
                        $values = collect($data['values'] ?? []);

                        if ($values->isEmpty()) {
                            return $query;
                        }

                        // El OR interno va agrupado para no escapar de visibleTo().
                        return $query->where(function (Builder $query) use ($values): void {
                            $priorities = $values->reject(fn ($value): bool => $value === 'none')->map(fn ($value): int => (int) $value);

                            if ($priorities->isNotEmpty()) {
                                $query->whereIn('priority', $priorities->all());
                            }

                            if ($values->contains('none')) {
                                $query->orWhereNull('priority');
                            }
                        });
                    }),

                SelectFilter::make('category_id')
                    ->label('Categoría')
                    ->relationship('category', 'name')
                    ->multiple()
                    ->preload(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    PrioritizeAction::make(),
                    AssignAction::make(),
                ]),
            ]);
    }

    private static function statusFlowOrder(): string
    {
        $cases = collect(RequestStatus::cases())
            ->map(fn (RequestStatus $status, int $position): string => "WHEN '{$status->value}' THEN {$position}")
            ->implode(' ');

        return "CASE status {$cases} END";
    }
}
