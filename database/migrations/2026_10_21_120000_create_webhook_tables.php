<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('url', 2048);
            $table->string('description', 191)->nullable();
            $table->jsonb('events');
            $table->text('secret'); // encrypted cast
            $table->boolean('is_active')->default(true);
            // Consecutive deliveries that gave up; reset by any success.
            $table->unsignedInteger('failure_streak')->default(0);
            $table->timestamp('disabled_at')->nullable();
            $table->string('disabled_reason', 191)->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'is_active']);
        });

        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('webhook_endpoint_id')->constrained()->cascadeOnDelete();
            $table->string('event', 64);
            $table->uuid('payload_id');
            $table->jsonb('payload');
            $table->string('state', 16)->default('pending'); // pending, success, failed
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->text('response_body')->nullable();
            $table->string('error', 500)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['webhook_endpoint_id', 'id']);
            $table->index(['company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhook_endpoints');
    }
};
