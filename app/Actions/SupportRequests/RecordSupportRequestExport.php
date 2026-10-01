<?php

namespace App\Actions\SupportRequests;

use App\Enums\AuditEvent;
use App\Filament\Exports\SupportRequestExporter;
use App\Models\SupportRequest;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Facades\Gate;

/**
 * HU12: registra en auditoria quien exporto, cuando y con que alcance
 * (Tech Plan §27.4). El evento no pertenece a una solicitud.
 *
 * La exportacion la ejecuta Filament; este servicio vuelve a comprobar el
 * permiso y deja el rastro dentro de la misma transaccion de la accion.
 */
class RecordSupportRequestExport
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $filters  filtros activos de la tabla
     */
    public function handle(User $user, Export $export, array $filters = [], ?string $search = null): string
    {
        Gate::forUser($user)->authorize('export', SupportRequest::class);

        return $this->audit->record(
            null,
            AuditEvent::Exported,
            metadata: [
                'export_id' => $export->getKey(),
                'format' => 'csv',
                'rows' => $export->total_rows,
                'columns' => array_map(
                    fn (ExportColumn $column): string => $column->getName(),
                    SupportRequestExporter::getColumns(),
                ),
                'filters' => $filters,
                'search' => filled($search) ? $search : null,
            ],
            actor: $user,
        );
    }
}
