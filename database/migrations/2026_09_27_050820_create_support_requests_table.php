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
            $table->string('status', 20)->default('new')->index();
            // 1 baja, 2 media, 3 alta; null = sin priorizar (HU04).
            $table->unsignedTinyInteger('priority')->nullable()->index();
            $table->timestamps();

            $table->index('created_at');
            $table->index(['status', 'priority']);
        });

        // SQLite no permite agregar CHECK con ALTER TABLE; en MySQL/PostgreSQL
        // las reglas de estado y prioridad tambien quedan protegidas en la base de datos.
        if (in_array(DB::getDriverName(), ['mysql', 'pgsql'], true)) {
            DB::statement("ALTER TABLE support_requests ADD CONSTRAINT support_requests_status_check CHECK (status IN ('new','assigned','in_progress','resolved','reopened','closed'))");
            DB::statement('ALTER TABLE support_requests ADD CONSTRAINT support_requests_priority_check CHECK (priority IS NULL OR priority BETWEEN 1 AND 3)');
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
