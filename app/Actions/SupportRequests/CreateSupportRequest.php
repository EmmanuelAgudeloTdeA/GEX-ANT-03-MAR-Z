<?php

namespace App\Actions\SupportRequests;

use App\Enums\AuditEvent;
use App\Enums\RequestStatus;
use App\Models\Category;
use App\Models\SupportRequest;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * HU02: crea una solicitud. El propietario, el estado inicial y la fecha los
 * fija el sistema; del usuario solo se reciben titulo, descripcion y categoria.
 */
class CreateSupportRequest
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array{title: string, description: string, category_id: int|string}  $data
     */
    public function handle(User $user, array $data): SupportRequest
    {
        Gate::forUser($user)->authorize('create', SupportRequest::class);

        if (! Category::query()->active()->whereKey($data['category_id'] ?? null)->exists()) {
            throw new DomainException('La categoría seleccionada no existe o está inactiva.');
        }

        return DB::transaction(function () use ($user, $data): SupportRequest {
            $request = new SupportRequest([
                'title' => $data['title'],
                'description' => $data['description'],
                'category_id' => $data['category_id'],
            ]);

            $request->requester_id = $user->getKey();
            $request->status = RequestStatus::Nuevo;
            $request->save();

            $this->audit->record(
                $request,
                AuditEvent::Created,
                ['status' => [null, RequestStatus::Nuevo]],
                actor: $user,
            );

            return $request;
        });
    }
}
