<?php

namespace App\Notifications;

use App\Filament\Resources\SupportRequests\SupportRequestResource;
use App\Models\SupportRequest;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Notifications\Notification;

/**
 * HU05: aviso en la campana del panel al agente asignado. Solo lleva ID,
 * prioridad y categoria; ni descripcion ni datos de otras personas.
 *
 * No va en cola a proposito: la notificacion de Filament por defecto se
 * encola y sin worker nunca llegaria.
 */
class SupportRequestAssigned extends Notification
{
    public function __construct(public SupportRequest $request) {}

    /**
     * @return array<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $request = $this->request->loadMissing('category');

        return FilamentNotification::make()
            ->title("Se te asignó la solicitud #{$request->getKey()}")
            ->body(sprintf(
                'Prioridad %s · Categoría %s',
                $request->priority?->getLabel() ?? 'sin definir',
                $request->category->name,
            ))
            ->icon(Heroicon::OutlinedInboxArrowDown)
            ->info()
            ->actions([
                Action::make('view')
                    ->label('Ver solicitud')
                    ->url(SupportRequestResource::getUrl('view', ['record' => $request]))
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
