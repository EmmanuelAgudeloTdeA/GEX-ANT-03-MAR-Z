<?php

namespace App\Filament\Actions;

use App\Actions\SupportRequests\RecordSupportRequestExport;
use App\Filament\Exports\SupportRequestExporter;
use App\Models\SupportRequest;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\Exports\Models\Export;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Contracts\HasTable;

/**
 * HU12: exportacion CSV en la cabecera de la lista. Usa la query de la tabla,
 * asi hereda visibleTo(), la busqueda y los filtros activos (Tech Plan §27.1).
 *
 * Las columnas son fijas (sin mapeo) para que nadie agregue texto libre o
 * datos de personas al reporte.
 */
class ExportSupportRequestsAction
{
    public static function make(): ExportAction
    {
        // Con la cola en sync, el job de Filament olvida la sesion al terminar;
        // por eso el usuario se toma antes de exportar.
        $user = null;

        return ExportAction::make()
            ->label('Exportar CSV')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->modalHeading('Exportar reporte de solicitudes')
            ->modalDescription('El reporte usa la búsqueda y los filtros activos de la tabla. No incluye título, descripción, comentarios ni datos de personas.')
            ->modalSubmitActionLabel('Exportar')
            ->exporter(SupportRequestExporter::class)
            ->formats([ExportFormat::Csv])
            ->columnMapping(false)
            // La exportacion y su registro en auditoria se guardan juntos.
            ->databaseTransaction()
            ->visible(fn (): bool => auth()->user()->can('export', SupportRequest::class))
            ->authorize(fn (): bool => auth()->user()->can('export', SupportRequest::class))
            ->before(function () use (&$user): void {
                $user = auth()->user();
            })
            ->after(function (HasTable $livewire) use (&$user): void {
                // Se devuelve el usuario a este request para que la pagina se
                // vuelva a pintar; la sesion guardada no se toca.
                if (! auth()->check()) {
                    auth()->setUser($user);
                }

                // Filament no expone la exportacion recien creada; es la ultima
                // de este usuario, creada dentro de esta misma transaccion.
                $export = Export::query()
                    ->whereBelongsTo($user)
                    ->where('exporter', SupportRequestExporter::class)
                    ->latest('id')
                    ->firstOrFail();

                app(RecordSupportRequestExport::class)->handle(
                    $user,
                    $export,
                    self::activeFilters($livewire->tableFilters ?? []),
                    $livewire->getTableSearch(),
                );
            });
    }

    /**
     * Deja solo los filtros con valor, para que el registro muestre el
     * alcance real de la exportacion.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private static function activeFilters(array $filters): array
    {
        return collect($filters)
            ->map(fn (mixed $state): mixed => is_array($state) ? array_filter($state, filled(...)) : $state)
            ->filter(filled(...))
            ->all();
    }
}
