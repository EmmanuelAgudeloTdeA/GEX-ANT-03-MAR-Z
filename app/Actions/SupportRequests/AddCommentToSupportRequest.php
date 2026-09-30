<?php

namespace App\Actions\SupportRequests;

use App\Enums\AuditEvent;
use App\Models\RequestComment;
use App\Models\SupportRequest;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * HU06: registra un comentario de avance en una solicitud.
 *
 * El autor es siempre el usuario que ejecuta la operacion y la fecha la pone el
 * sistema; del formulario solo llega el texto. El comentario se crea junto con
 * su auditoria en la misma transaccion y no se puede editar ni borrar despues
 * (BR-09, BR-10).
 */
class AddCommentToSupportRequest
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(User $user, SupportRequest $request, string $body): RequestComment
    {
        Gate::forUser($user)->authorize('comment', $request);

        // Un comentario de solo espacios no es un comentario (Tech Plan §19.2).
        if (trim($body) === '') {
            throw new DomainException('El comentario no puede estar vacío.');
        }

        return DB::transaction(function () use ($user, $request, $body): RequestComment {
            $comment = new RequestComment(['body' => trim($body)]);
            $comment->supportRequest()->associate($request);
            $comment->author()->associate($user);
            $comment->save();

            // El texto del comentario no se copia a la auditoria, solo su id.
            $this->audit->record(
                $request,
                AuditEvent::CommentAdded,
                metadata: ['comment_id' => $comment->getKey()],
                actor: $user,
            );

            return $comment;
        });
    }
}
