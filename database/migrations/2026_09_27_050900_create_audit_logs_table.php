<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            // Nullable solo para eventos que no pertenecen a una solicitud (exportacion).
            $table->foreignId('support_request_id')->nullable()->constrained()->restrictOnDelete();
            // Nullable = evento del sistema (sin usuario autenticado).
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('actor_role', 30);
            $table->string('event', 40)->index();
            $table->string('field', 60)->nullable();
            $table->string('old_value', 255)->nullable();
            $table->string('new_value', 255)->nullable();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->uuid('batch_id')->index();
            // Registro inmutable: no tiene updated_at.
            $table->timestamp('created_at')->index();

            $table->index(['support_request_id', 'created_at']);
            $table->index('actor_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
