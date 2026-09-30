<?php

namespace App\Filament\Actions;

use App\Actions\SupportRequests\ReopenSupportRequest;
use App\Models\SupportRequest;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

class ReopenSolutionAction
{
    public static function make(): Action
    {
        return Action::make('reopenSolution')
            ->label('Reabrir solicitud')
            ->modalHeading('Reabrir solicitud')
            ->modalDescription(
                'Indica por qué la solución no resolvió tu solicitud.'
            )
            ->modalSubmitActionLabel('Reabrir solicitud')
            ->visible(
                fn (SupportRequest $record): bool => auth()->user()->can(
                    'reopen',
                    $record
                )
            )
            ->authorize(
                fn (SupportRequest $record): bool => auth()->user()->can(
                    'reopen',
                    $record
                )
            )
            ->schema([
                Textarea::make('reason')
                    ->label('Motivo de reapertura')
                    ->placeholder('Explica por qué necesitas reabrir la solicitud...')
                    ->helperText(
                        'Debes indicar claramente el motivo de la reapertura.'
                    )
                    ->required()
                    ->maxLength(2000)
                    ->rows(5),
            ])
            ->action(function (
                SupportRequest $record,
                array $data,
                Action $action
            ): void {
                try {
                    app(ReopenSupportRequest::class)->handle(
                        auth()->user(),
                        $record,
                        $data['reason']
                    );
                } catch (DomainException $exception) {
                    Notification::make()
                        ->title('No se pudo reabrir la solicitud')
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();

                    $action->halt();
                }

                $record->refresh();

                Notification::make()
                    ->title('Solicitud reabierta')
                    ->body('La solicitud fue reabierta correctamente.')
                    ->success()
                    ->send();
            });
    }
}