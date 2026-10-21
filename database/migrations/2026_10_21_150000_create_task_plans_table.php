<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One courier's ordered route for one slot (QDelivery "task plan").
        Schema::create('task_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hub_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('courier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('slot', 16)->nullable();
            $table->string('status_slug', 20)->default('planned'); // planned, in_progress, completed, cancelled
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('quantity')->default(0);
            $table->unsignedInteger('distance')->default(0); // m
            $table->unsignedInteger('duration')->default(0); // s, driving only
            $table->jsonb('start')->nullable();               // [lat, lng]
            $table->jsonb('end')->nullable();
            $table->jsonb('geometry')->nullable();            // [[lat, lng], …]
            $table->string('solver', 20)->nullable();         // vroom, insertion, manual
            $table->string('router', 20)->nullable();         // osrm, haversine
            $table->timestamps();

            $table->index(['company_id', 'slot']);
            $table->index(['company_id', 'courier_id', 'status_slug']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            // Planned arrival at this stop, from the route plan.
            $table->timestamp('eta_at')->nullable()->after('duration');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', fn (Blueprint $table) => $table->dropColumn('eta_at'));
        Schema::dropIfExists('task_plans');
    }
};
