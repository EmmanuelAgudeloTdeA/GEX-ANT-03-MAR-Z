<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * HU06: comentarios de trabajo. Autor y fecha inmutables; sin updated_at.
     */
    public function up(): void
    {
        Schema::create('request_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->text('body');
            $table->timestamp('created_at');

            $table->index(['support_request_id', 'created_at']);
        });

        if (in_array(DB::getDriverName(), ['mysql', 'pgsql'], true)) {
            DB::statement('ALTER TABLE request_comments ADD CONSTRAINT request_comments_body_check CHECK (length(trim(body)) > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('request_comments');
    }
};
