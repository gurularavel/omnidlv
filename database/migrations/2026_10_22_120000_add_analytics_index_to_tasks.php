<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Analytics read finished tasks by the day they ended.
    public function up(): void
    {
        Schema::table('tasks', fn (Blueprint $table) => $table->index(['company_id', 'ended_at']));
    }

    public function down(): void
    {
        Schema::table('tasks', fn (Blueprint $table) => $table->dropIndex(['company_id', 'ended_at']));
    }
};
