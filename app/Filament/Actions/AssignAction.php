<?php

namespace App\Filament\Actions;

use App\Actions\SupportRequests\AssignSupportRequest;
use App\Models\SupportRequest;
use App\Models\User;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * HU05: la accion solo ofrece agentes activos; el servicio vuelve a validar,
 * guarda, audita y notifica. Se usa en la fila de la tabla y en el detalle.
 */
class AssignAction
{
    public static function make(): Action
    {
        return Action::make('assign')
            ->label(fn (SupportRequest $record): string => $record->assigned_agent_id ? 'Reasignar' : 'Asignar')
            ->icon(Heroicon::OutlinedUserPlus)
            ->modalHeading(fn (SupportRequest $record): string => $record->assigned_agent_id ? 'Reasignar solicitud' : 'Asignar solicitud')
            ->modalSubmitActionLabel('Asignar')
            ->visible(fn (SupportRequest $record): bool => auth()->user()->can('assign', $record))
            ->authorize(fn (SupportRequest $record): bool => auth()->user()->can('assign', $record))
            ->schema([
                Select::make('assigned_agent_id')
                    ->label('Agente')
                    ->options(fn (SupportRequest $record): array => User::query()
                        ->activeAgents()
                        ->when($record->assigned_agent_id, fn ($query, int $current) => $query->whereKeyNot($current))
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->required()
                    ->helperText('Solo se muestran agentes activos.'),
            ])
            ->action(function (SupportRequest $record, array $data, Action $action): void {
                $agent = User::findOrFail($data['assigned_agent_id']);

                try {
                    app(AssignSupportRequest::class)->handle(auth()->user(), $record, $agent);
                } catch (DomainException $exception) {
                    Notification::make()
                        ->title('No se pudo asignar la solicitud')
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();

                    $action->halt();
                }

                $record->refresh();

                Notification::make()
                    ->title("Solicitud asignada a {$agent->name}")
                    ->success()
                    ->send();
            });
    }
}
