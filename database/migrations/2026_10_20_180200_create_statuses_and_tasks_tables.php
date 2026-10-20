<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // company_id NULL = system status shared by every company; companies may add their own
        // sub-statuses inside the fixed categories (see App\Enums\StatusCategory).
        Schema::create('statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('status_category_slug', 20);
            $table->string('slug', 50);
            $table->jsonb('name');
            $table->jsonb('description')->nullable();
            $table->string('color', 9)->default('#64748b');
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('notify_recipient')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['company_id', 'status_category_slug']);
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('hub_id')->constrained()->restrictOnDelete();
            $table->string('integration_id')->nullable();
            $table->string('tracking_code', 32)->unique();
            $table->string('type_slug', 20)->default('drop_off');
            $table->string('obligation_slug', 20)->default('mandatory');
            $table->string('channel', 20)->default('panel');

            $table->foreignId('status_id')->constrained('statuses');
            $table->string('status_category_slug', 20);
            $table->timestamp('status_at')->nullable();

            $table->string('assignment_type', 30)->default('pool');
            $table->jsonb('assignment_options')->default('{}');
            $table->jsonb('slot_options')->default('{}');
            $table->foreignId('courier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('plan_id')->nullable()->index();

            $table->string('slot', 16)->nullable();
            $table->timestamp('slot_starts_at')->nullable();
            $table->timestamp('slot_ends_at')->nullable();
            $table->unsignedSmallInteger('slot_auto_closing_time')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');

            foreach (['origin', 'destination'] as $side) {
                $table->foreignId("{$side}_hub_id")->nullable()->constrained('hubs')->nullOnDelete();
                $table->string("{$side}_address", 500)->nullable();
                $table->string("{$side}_address_building")->nullable();
                $table->string("{$side}_address_floor")->nullable();
                $table->string("{$side}_address_apartment")->nullable();
                $table->string("{$side}_address_description", 500)->nullable();
                $table->string("{$side}_name")->nullable();
                $table->string("{$side}_phone_country", 2)->nullable();
                $table->string("{$side}_phone", 20)->nullable();
                $table->decimal("{$side}_lat", 9, 6)->nullable();
                $table->decimal("{$side}_lng", 9, 6)->nullable();
                $table->boolean("is_{$side}_resolved")->default(false);
            }

            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('unit_quantity')->default(0);
            $table->unsignedSmallInteger('service_time')->default(0);
            $table->unsignedSmallInteger('response_time')->default(0);
            $table->smallInteger('priority')->default(0);
            $table->text('notes')->nullable();
            $table->text('recipient_notes')->nullable();

            $table->boolean('requires_delivery_code')->default(false);
            $table->boolean('requires_delivery_code_mandatory')->default(false);
            $table->text('delivery_code')->nullable(); // encrypted cast: must stay re-sendable by SMS
            $table->unsignedTinyInteger('delivery_code_attempts')->default(0);
            $table->boolean('requires_pod_photo')->default(false);
            $table->boolean('requires_pod_photo_mandatory')->default(false);
            $table->boolean('required_dont_ring_the_bell')->default(false);
            $table->boolean('requires_contactless')->default(false);

            $table->boolean('is_locked')->default(false);
            $table->boolean('is_confirmed')->default(true);
            $table->string('payment_type', 20)->nullable();
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->jsonb('tags')->default('[]');

            $table->unsignedInteger('route_order')->default(0);
            $table->unsignedInteger('distance')->default(0);
            $table->unsignedInteger('duration')->default(0);
            $table->unsignedTinyInteger('rating')->default(0);
            $table->text('comment')->nullable();
            $table->boolean('was_finalized_duly')->default(false);
            $table->boolean('was_delayed')->default(false);

            $table->timestamp('seen_at')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status_category_slug', 'ends_at']);
            $table->index(['company_id', 'courier_id', 'status_category_slug']);
            $table->index(['company_id', 'hub_id', 'slot']);
        });

        Schema::create('task_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->string('integration_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('currency', 3)->nullable();
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('barcode', 128)->nullable();
            $table->timestamp('scanned_at')->nullable();
            $table->timestamps();

            $table->unique(['task_id', 'integration_id']);
        });

        Schema::create('task_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('status_id')->constrained('statuses');
            $table->string('status_category_slug', 20);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('courier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('role_slug', 20)->nullable();
            $table->string('channel', 20);
            $table->string('notes', 255)->nullable();
            $table->decimal('lat', 9, 6)->nullable();
            $table->decimal('lng', 9, 6)->nullable();
            $table->timestamps();

            $table->index(['task_id', 'created_at']);
        });

        DB::statement('CREATE UNIQUE INDEX tasks_company_integration_type_unique ON tasks (company_id, integration_id, type_slug) WHERE integration_id IS NOT NULL AND deleted_at IS NULL');
        DB::statement('CREATE INDEX tasks_tags_gin ON tasks USING GIN (tags)');
    }

    public function down(): void
    {
        Schema::dropIfExists('task_status_logs');
        Schema::dropIfExists('task_items');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('statuses');
    }
};
