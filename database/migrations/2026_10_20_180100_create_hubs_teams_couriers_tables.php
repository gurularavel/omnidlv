<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hubs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('integration_id')->nullable();
            $table->string('name');
            $table->string('address', 500);
            $table->string('address_building')->nullable();
            $table->string('address_apartment')->nullable();
            $table->string('address_floor')->nullable();
            $table->decimal('lat', 9, 6);
            $table->decimal('lng', 9, 6);
            $table->jsonb('tags')->default('[]');
            $table->timestamps();
            $table->softDeletes();

            $table->index('company_id');
        });

        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hub_id')->nullable()->constrained()->nullOnDelete();
            $table->string('integration_id')->nullable();
            $table->string('name');
            $table->boolean('is_default')->default(false);
            $table->string('color', 9)->default('#0f766e');
            // Polygon is stored as [[lat, lng], ...]; the bbox columns let point-in-polygon
            // skip most teams with an indexed range check before the exact test in PHP.
            $table->jsonb('polygon')->nullable();
            $table->decimal('min_lat', 9, 6)->nullable();
            $table->decimal('max_lat', 9, 6)->nullable();
            $table->decimal('min_lng', 9, 6)->nullable();
            $table->decimal('max_lng', 9, 6)->nullable();
            $table->jsonb('tags')->default('[]');
            $table->timestamps();
            $table->softDeletes();

            $table->index('company_id');
        });

        Schema::create('team_user', function (Blueprint $table) {
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['team_id', 'user_id']);
        });

        Schema::create('courier_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('integration_id')->nullable();
            $table->string('code', 50)->nullable();
            $table->string('vehicle_type_slug', 20)->default('motorcycle');
            $table->string('vehicle_description')->nullable();
            $table->string('vehicle_license_plate', 20)->nullable();
            $table->string('vehicle_color', 50)->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->unsignedInteger('pool_capacity')->nullable();
            $table->text('notes')->nullable();
            $table->jsonb('tags')->default('[]');
            $table->decimal('rating', 2, 1)->default(0);
            $table->unsignedInteger('scored_tasks_count')->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'user_id']);
        });

        Schema::create('courier_locations', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->boolean('is_online')->default(false);
            $table->boolean('is_working')->default(false);
            $table->boolean('on_duty')->default(false);
            $table->decimal('lat', 9, 6)->nullable();
            $table->decimal('lng', 9, 6)->nullable();
            $table->unsignedSmallInteger('heading')->nullable();
            $table->decimal('speed', 6, 2)->nullable();
            $table->unsignedInteger('accuracy')->nullable();
            $table->unsignedTinyInteger('battery')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('courier_location_pings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('lat', 9, 6);
            $table->decimal('lng', 9, 6);
            $table->unsignedSmallInteger('heading')->nullable();
            $table->decimal('speed', 6, 2)->nullable();
            $table->unsignedInteger('accuracy')->nullable();
            $table->timestamp('recorded_at');

            $table->index(['user_id', 'recorded_at']);
        });

        Schema::create('courier_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->decimal('start_lat', 9, 6)->nullable();
            $table->decimal('start_lng', 9, 6)->nullable();
            $table->timestamps();

            $table->index(['company_id', 'user_id', 'started_at']);
        });

        DB::statement('CREATE UNIQUE INDEX hubs_company_integration_unique ON hubs (company_id, integration_id) WHERE integration_id IS NOT NULL AND deleted_at IS NULL');
        DB::statement('CREATE INDEX teams_bbox_index ON teams (company_id, min_lat, max_lat, min_lng, max_lng) WHERE polygon IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('courier_shifts');
        Schema::dropIfExists('courier_location_pings');
        Schema::dropIfExists('courier_locations');
        Schema::dropIfExists('courier_details');
        Schema::dropIfExists('team_user');
        Schema::dropIfExists('teams');
        Schema::dropIfExists('hubs');
    }
};
