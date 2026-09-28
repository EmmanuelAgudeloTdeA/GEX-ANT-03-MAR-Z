<?php

namespace App\Filament\Actions;

use App\Actions\SupportRequests\PrioritizeSupportRequest;
use App\Enums\RequestPriority;
use App\Models\SupportRequest;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * HU04: la accion solo recoge la prioridad; el servicio valida, guarda y
 * audita. Se usa en la fila de la tabla y en la cabecera del detalle.
 */
class PrioritizeAction
{
    public static function make(): Action
    {
        return Action::make('prioritize')
            ->label('Priorizar')
            ->icon(Heroicon::OutlinedFlag)
            ->modalHeading('Priorizar solicitud')
            ->modalSubmitActionLabel('Guardar prioridad')
            ->visible(fn (SupportRequest $record): bool => auth()->user()->can('prioritize', $record))
            ->authorize(fn (SupportRequest $record): bool => auth()->user()->can('prioritize', $record))
            ->fillForm(fn (SupportRequest $record): array => [
                'priority' => $record->priority?->value,
            ])
            ->schema([
                Select::make('priority')
                    ->label('Prioridad')
                    ->options(RequestPriority::class)
                    ->required(),
            ])
            ->action(function (SupportRequest $record, array $data): void {
                $priority = $data['priority'] instanceof RequestPriority
                    ? $data['priority']
                    : RequestPriority::from((int) $data['priority']);

                app(PrioritizeSupportRequest::class)->handle(auth()->user(), $record, $priority);

                $record->refresh();

                Notification::make()
                    ->title("Prioridad actualizada a {$priority->getLabel()}")
                    ->success()
                    ->send();
            });
    }
}
