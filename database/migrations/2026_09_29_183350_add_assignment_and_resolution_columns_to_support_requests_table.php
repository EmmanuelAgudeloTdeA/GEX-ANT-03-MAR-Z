<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sprint 2: responsable de la solicitud (HU05) y fechas de resolucion y
     * cierre (HU07, HU08; base del tiempo de ciclo de HU10).
     */
    public function up(): void
    {
        Schema::table('support_requests', function (Blueprint $table) {
            $table->foreignId('assigned_agent_id')->nullable()->after('priority')->constrained('users')->restrictOnDelete();
            // Quien asigno y cuando (HU05).
            $table->foreignId('assigned_by_id')->nullable()->after('assigned_agent_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('assigned_at')->nullable()->after('assigned_by_id');
            // Ultima resolucion y cierre por confirmacion del solicitante.
            $table->timestamp('resolved_at')->nullable()->after('assigned_at');
            $table->timestamp('closed_at')->nullable()->after('resolved_at');

            $table->index('assigned_agent_id');
        });

        // Igual que en la tabla base: SQLite no permite agregar CHECK con ALTER TABLE.
        if (in_array(DB::getDriverName(), ['mysql', 'pgsql'], true)) {
            // Ninguna solicitud avanza sin responsable.
            DB::statement("ALTER TABLE support_requests ADD CONSTRAINT support_requests_agent_check CHECK (status = 'new' OR assigned_agent_id IS NOT NULL)");
            DB::statement("ALTER TABLE support_requests ADD CONSTRAINT support_requests_closed_check CHECK (status <> 'closed' OR closed_at IS NOT NULL)");
        }
    }

    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'pgsql'], true)) {
            $drop = DB::getDriverName() === 'mysql' ? 'DROP CHECK' : 'DROP CONSTRAINT';

            DB::statement("ALTER TABLE support_requests {$drop} support_requests_agent_check");
            DB::statement("ALTER TABLE support_requests {$drop} support_requests_closed_check");
        }

        Schema::table('support_requests', function (Blueprint $table) {
            // Primero las FK, luego el indice propio y al final las columnas.
            $table->dropForeign(['assigned_agent_id']);
            $table->dropForeign(['assigned_by_id']);
            $table->dropIndex(['assigned_agent_id']);
            $table->dropColumn(['assigned_agent_id', 'assigned_by_id', 'assigned_at', 'resolved_at', 'closed_at']);
        });
    }
};
