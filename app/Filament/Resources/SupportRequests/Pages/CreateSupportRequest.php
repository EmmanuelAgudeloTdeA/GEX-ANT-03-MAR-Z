<?php

namespace App\Filament\Resources\SupportRequests\Pages;

use App\Actions\SupportRequests\CreateSupportRequest as CreateSupportRequestAction;
use App\Filament\Resources\SupportRequests\SupportRequestResource;
use DomainException;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class CreateSupportRequest extends CreateRecord
{
    protected static string $resource = SupportRequestResource::class;

    protected static bool $canCreateAnother = false;

    /**
     * La pagina solo recoge datos; el servicio fija propietario, estado y
     * escribe la auditoria en la misma transaccion.
     */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(CreateSupportRequestAction::class)->handle(auth()->user(), $data);
        } catch (DomainException $exception) {
            Notification::make()
                ->title($exception->getMessage())
                ->danger()
                ->send();

            throw new Halt;
        }
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Solicitud creada';
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
