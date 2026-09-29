<?php

namespace App\Filament\Widgets;

use App\Enums\RequestStatus;
use App\Filament\Resources\SupportRequests\SupportRequestResource;
use App\Models\SupportRequest;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

/**
 * HU04/HU05 (mejora UX, Tech Plan §18): lo que espera una decision del
 * coordinador. Estado operativo actual, sin desglose por persona (BR-17).
 * Cada stat enlaza a la lista filtrada.
 */
class TriageStats extends StatsOverviewWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 1;

    protected ?string $heading = 'Triaje';

    protected ?string $description = 'Solicitudes que esperan una decisión de coordinación.';

    protected function getStats(): array
    {
        $open = array_map(fn (RequestStatus $status): string => $status->value, RequestStatus::open());

        // TODO CC Sprint 2: agregar "Alta con fecha objetivo vencida" cuando exista target_date.
        return [
            Stat::make('Por priorizar', $this->visible()->whereIn('status', $open)->whereNull('priority')->count())
                ->description('Abiertas sin prioridad')
                ->descriptionIcon(Heroicon::OutlinedFlag)
                ->icon(Heroicon::OutlinedQueueList)
                ->color('warning')
                ->url($this->listUrl(['status' => $open, 'priority' => ['none']])),

            Stat::make('Por asignar', $this->visible()->where('status', RequestStatus::New)->count())
                ->description('Nuevas sin agente responsable')
                ->descriptionIcon(Heroicon::OutlinedUserPlus)
                ->icon(Heroicon::OutlinedInboxStack)
                ->color('info')
                ->url($this->listUrl(['status' => [RequestStatus::New->value]])),

            Stat::make('Reabiertas', $this->visible()->where('status', RequestStatus::Reopened)->count())
                ->description('El solicitante no quedó conforme')
                ->descriptionIcon(Heroicon::OutlinedArrowPath)
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->color('danger')
                ->url($this->listUrl(['status' => [RequestStatus::Reopened->value]])),
        ];
    }

    private function visible(): Builder
    {
        return SupportRequest::query()->visibleTo(auth()->user());
    }

    /**
     * @param  array<string, array<string>>  $filters  filtro => valores
     */
    private function listUrl(array $filters): string
    {
        return SupportRequestResource::getUrl('index', [
            'filters' => collect($filters)->map(fn (array $values): array => ['values' => $values])->all(),
        ]);
    }
}
