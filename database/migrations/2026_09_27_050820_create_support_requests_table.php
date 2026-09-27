<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('support_requests', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->text('description');
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('requester_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('nuevo')->index();
            $table->timestamps();

            $table->index('created_at');
        });

        // SQLite no permite agregar CHECK con ALTER TABLE; en MySQL/PostgreSQL
        // la regla de estados tambien queda protegida en la base de datos.
        if (in_array(DB::getDriverName(), ['mysql', 'pgsql'], true)) {
            DB::statement("ALTER TABLE support_requests ADD CONSTRAINT support_requests_status_check CHECK (status IN ('nuevo','asignada','en_progreso','resuelta','reabierta','cerrada'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('support_requests');
    }
};
