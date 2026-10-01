<?php

namespace App\Filament\Exports;

use App\Enums\AuditEvent;
use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use App\Models\SupportRequest;
use Carbon\CarbonInterface;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

/**
 * HU12 + cambio controlado del Sprint 3: columnas fijas para analisis
 * agregado (Tech Plan §27.2).
 *
 * Quedan fuera a proposito las credenciales, el texto libre (titulo,
 * descripcion, comentarios, motivos) y las personas (agente y solicitante,
 * PD-13), para que el reporte no permita armar rankings individuales.
 */
class SupportRequestExporter extends Exporter
{
    protected static ?string $model = SupportRequest::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),
            ExportColumn::make('category.name')
                ->label('Categoría'),
            ExportColumn::make('status')
                ->label('Estado')
                ->formatStateUsing(fn (RequestStatus $state): string => $state->getLabel()),
            ExportColumn::make('priority')
                ->label('Prioridad')
                ->formatStateUsing(fn (?RequestPriority $state): string => $state?->getLabel() ?? 'Sin priorizar'),
            ExportColumn::make('created_at')
                ->label('Creada')
                ->formatStateUsing(fn (?CarbonInterface $state): ?string => static::formatDate($state)),
            ExportColumn::make('assigned_at')
                ->label('Asignada')
                ->formatStateUsing(fn (?CarbonInterface $state): ?string => static::formatDate($state)),
            ExportColumn::make('resolved_at')
                ->label('Resuelta')
                ->formatStateUsing(fn (?CarbonInterface $state): ?string => static::formatDate($state)),
            ExportColumn::make('closed_at')
                ->label('Cerrada')
                ->formatStateUsing(fn (?CarbonInterface $state): ?string => static::formatDate($state)),
            ExportColumn::make('cycle_time_hours')
                ->label('Tiempo de ciclo (horas)')
                ->state(fn (SupportRequest $record): ?float => $record->cycleTimeInHours()),
            ExportColumn::make('reopenings_count')
                ->label('Reaperturas'),
        ];
    }

    /**
     * Solo se cargan la categoria y el conteo de reaperturas; las relaciones
     * con personas que trae la tabla no se necesitan en el reporte.
     */
    public static function modifyQuery(Builder $query): Builder
    {
        return $query
            ->without(['requester', 'assignedAgent'])
            ->with('category')
            ->withCount([
                // Cada reapertura deja una fila de auditoria por campo; se
                // cuenta solo la del estado para contarla una vez.
                'auditLogs as reopenings_count' => fn (Builder $query) => $query
                    ->where('event', AuditEvent::Reopened)
                    ->where('field', 'status'),
            ]);
    }

    public function getFormats(): array
    {
        return [ExportFormat::Csv];
    }

    public function getFileName(Export $export): string
    {
        return "reporte-solicitudes-{$export->getKey()}";
    }

    public static function getCompletedNotificationTitle(Export $export): string
    {
        return 'Reporte de solicitudes listo';
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $rows = $export->successful_rows;

        $body = 'Se exportaron '.Number::format($rows).' '.($rows === 1 ? 'solicitud' : 'solicitudes').'.';

        if ($failed = $export->getFailedRowsCount()) {
            $body .= ' '.Number::format($failed).' no se pudieron exportar.';
        }

        return $body;
    }

    private static function formatDate(?CarbonInterface $date): ?string
    {
        return $date?->format('Y-m-d H:i');
    }
}
