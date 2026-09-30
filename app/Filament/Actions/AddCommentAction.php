<?php

namespace App\Filament\Actions;

use App\Actions\SupportRequests\AddCommentToSupportRequest;
use App\Models\SupportRequest;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * HU06: la accion solo recoge el texto del comentario; el autor, la fecha, el
 * guardado y la auditoria los pone el servicio de dominio.
 *
 * Se usa como accion de cabecera del RelationManager de comentarios, por eso
 * recibe la solicitud: en un RelationManager la accion de cabecera no tiene un
 * registro propio.
 */
class AddCommentAction
{
    public static function make(SupportRequest $request): Action
    {
        return Action::make('addComment')
            ->label('Agregar comentario')
            ->icon(Heroicon::OutlinedChatBubbleLeftRight)
            ->modalHeading('Registrar comentario')
            ->modalDescription('El comentario queda registrado con tu usuario y la fecha actual, y ya no se puede editar.')
            ->modalSubmitActionLabel('Registrar comentario')
            ->visible(fn (): bool => auth()->user()->can('comment', $request))
            ->authorize(fn (): bool => auth()->user()->can('comment', $request))
            ->schema([
                Textarea::make('body')
                    ->label('Comentario')
                    // `required` tambien rechaza un texto formado solo por espacios.
                    ->required()
                    ->autosize()
                    ->rows(4),
            ])
            ->action(function (array $data, Action $action) use ($request): void {
                try {
                    app(AddCommentToSupportRequest::class)->handle(auth()->user(), $request, $data['body']);
                } catch (DomainException $exception) {
                    Notification::make()
                        ->title('No se pudo registrar el comentario')
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();

                    $action->halt();
                }

                $request->refresh();

                Notification::make()
                    ->title('Comentario registrado')
                    ->success()
                    ->send();
            });
    }
}
