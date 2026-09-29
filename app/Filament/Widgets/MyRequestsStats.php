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
use Illuminate\Support\Carbon;

/**
 * HU03 (mejora UX, Tech Plan §18): resumen del estado de las solicitudes del
 * solicitante. Cada stat enlaza a la lista filtrada por esos estados.
 */
class MyRequestsStats extends StatsOverviewWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 1;

    protected ?string $heading = 'Mis solicitudes';

    protected ?string $description = 'Resumen del estado de las solicitudes que has creado.';

    protected function getStats(): array
    {
        $counts = $this->ownRequests()
            ->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $count = fn (array $statuses): int => collect($statuses)
            ->sum(fn (RequestStatus $status): int => (int) ($counts[$status->value] ?? 0));

        return [
            Stat::make('Abiertas', $count(RequestStatus::open()))
                ->description('Nuevas, asignadas, en progreso o reabiertas')
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->icon(Heroicon::OutlinedInboxStack)
                ->color('info')
                ->chart($this->createdLastSevenDays())
                ->url($this->listUrl(RequestStatus::open())),

            Stat::make('Por confirmar', $count([RequestStatus::Resolved]))
                ->description('Resueltas: confirma la solución o reábrelas')
                ->descriptionIcon(Heroicon::OutlinedHandRaised)
                ->icon(Heroicon::OutlinedCheckBadge)
                ->color('warning')
                ->url($this->listUrl([RequestStatus::Resolved])),

            Stat::make('Cerradas', $count([RequestStatus::Closed]))
                ->description('Solución confirmada')
                ->descriptionIcon(Heroicon::OutlinedCheckCircle)
                ->icon(Heroicon::OutlinedArchiveBox)
                ->color('success')
                ->url($this->listUrl([RequestStatus::Closed])),
        ];
    }

    /**
     * Solicitudes creadas por el usuario, siempre dentro de lo que puede ver.
     */
    private function ownRequests(): Builder
    {
        $user = auth()->user();

        return SupportRequest::query()
            ->visibleTo($user)
            ->where('requester_id', $user->getKey());
    }

    /**
     * @param  array<RequestStatus>  $statuses
     */
    private function listUrl(array $statuses): string
    {
        return SupportRequestResource::getUrl('index', [
            'filters' => [
                'status' => [
                    'values' => array_map(fn (RequestStatus $status): string => $status->value, $statuses),
                ],
            ],
        ]);
    }

    /**
     * Solicitudes creadas por dia en los ultimos 7 dias, para la mini grafica.
     *
     * @return array<int>
     */
    private function createdLastSevenDays(): array
    {
        $from = Carbon::today()->subDays(6);

        $perDay = $this->ownRequests()
            ->where('created_at', '>=', $from)
            ->pluck('created_at')
            ->countBy(fn (Carbon $createdAt): string => $createdAt->toDateString());

        return collect(range(0, 6))
            ->map(fn (int $offset): int => $perDay[$from->copy()->addDays($offset)->toDateString()] ?? 0)
            ->all();
    }
}
