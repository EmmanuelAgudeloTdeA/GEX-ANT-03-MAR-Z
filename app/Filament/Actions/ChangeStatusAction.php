<?php

namespace App\Filament\Actions;

use App\Actions\SupportRequests\ChangeRequestStatus;
use App\Enums\RequestStatus;
use App\Models\SupportRequest;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

class ChangeStatusAction
{
    public static function make(): Action
    {
        return Action::make('changeStatus')
            ->label('Cambiar estado')
            ->icon(Heroicon::OutlinedArrowPath)
            ->modalHeading('Cambiar estado de solicitud')
            ->modalSubmitActionLabel('Guardar estado')
            ->visible(fn (SupportRequest $record): bool => auth()->user()->can('changeStatus', $record))
            ->authorize(fn (SupportRequest $record): bool => auth()->user()->can('changeStatus', $record))
            ->schema([
                Select::make('status')
                    ->label('Nuevo estado')
                    ->options(fn (SupportRequest $record): array => collect(
                        $record->status->allowedTargetsFor(auth()->user(), $record)
                    )
                        ->reject(fn (RequestStatus $status): bool => $status === RequestStatus::Assigned)
                        ->mapWithKeys(fn (RequestStatus $status): array => [$status->value => $status->getLabel()])
                        ->all())
                    ->required(),
            ])
            ->action(function (SupportRequest $record, array $data, Action $action): void {
                $status = $data['status'] instanceof RequestStatus
                    ? $data['status']
                    : RequestStatus::from($data['status']);

                try {
                    app(ChangeRequestStatus::class)->handle(auth()->user(), $record, $status);
                } catch (DomainException $exception) {
                    Notification::make()
                        ->title('No se pudo cambiar el estado')
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();

                    $action->halt();
                }

                $record->refresh();

                Notification::make()
                    ->title("Estado actualizado a {$status->getLabel()}")
                    ->success()
                    ->send();
            });
    }
}
