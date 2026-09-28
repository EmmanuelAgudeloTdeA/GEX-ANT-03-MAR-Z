<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_requests', function (Blueprint $table): void {
            $table->string('priority')->default('Media')->after('status');
        });

        Schema::create('support_request_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('support_request_id')
                ->constrained('support_requests')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('field');
            $table->string('old_value')->nullable();
            $table->string('new_value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_request_audits');

        Schema::table('support_requests', function (Blueprint $table): void {
            $table->dropColumn('priority');
        });
    }
};
