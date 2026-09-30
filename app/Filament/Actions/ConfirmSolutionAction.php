<?php

namespace App\Filament\Actions;

use App\Actions\SupportRequests\ConfirmSupportRequest;
use App\Models\SupportRequest;
use DomainException;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class ConfirmSolutionAction
{
    public static function make(): Action
    {
        return Action::make('confirmSolution')
            ->label('Confirmar solución')
            ->modalHeading('Confirmar solución')
            ->modalDescription(
                'Al confirmar la solución, la solicitud se cerrará definitivamente.'
            )
            ->modalSubmitActionLabel('Confirmar solución')
            ->visible(
                fn (SupportRequest $record): bool => auth()->user()->can(
                    'confirm',
                    $record
                )
            )
            ->authorize(
                fn (SupportRequest $record): bool => auth()->user()->can(
                    'confirm',
                    $record
                )
            )
            ->action(function (SupportRequest $record, Action $action): void {
                try {
                    app(ConfirmSupportRequest::class)->handle(
                        auth()->user(),
                        $record
                    );
                } catch (DomainException $exception) {
                    Notification::make()
                        ->title('No se pudo confirmar la solución')
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();

                    $action->halt();

                    return;
                }

                $record->refresh();

                Notification::make()
                    ->title('Solución confirmada')
                    ->body('La solicitud fue cerrada definitivamente.')
                    ->success()
                    ->send();
            });
    }
}