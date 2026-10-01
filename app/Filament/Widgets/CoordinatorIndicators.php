<?php

namespace App\Filament\Widgets;

use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use App\Models\SupportRequest;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CoordinatorIndicators extends StatsOverviewWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 0;

    protected ?string $heading = 'Indicadores de solicitudes';

    public function getSectionContentComponent(): Component
    {
        return Section::make($this->getHeading())
            ->schema($this->getCachedStats())
            ->columns($this->getColumns())
            ->contained(false)
            ->gridContainer()
            ->collapsed();
    }

    protected function getStats(): array
    {
        $requests = SupportRequest::query();
        $statusCounts = (clone $requests)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $priorityCounts = (clone $requests)
            ->selectRaw('priority, count(*) as total')
            ->groupBy('priority')
            ->pluck('total', 'priority');

        $stats = [Stat::make('Total de solicitudes', $requests->count())];

        foreach (RequestStatus::cases() as $status) {
            $stats[] = Stat::make($status->getLabel(), (int) ($statusCounts[$status->value] ?? 0));
        }

        foreach (RequestPriority::cases() as $priority) {
            $stats[] = Stat::make($priority->getLabel(), (int) ($priorityCounts[$priority->value] ?? 0));
        }

        $stats[] = Stat::make('Sin prioridad', (int) ($priorityCounts[null] ?? 0));

        return $stats;
    }
}
