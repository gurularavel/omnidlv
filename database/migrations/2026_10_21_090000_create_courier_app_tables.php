<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->string('to', 20);
            $table->string('purpose', 30);
            $table->text('body');
            $table->string('driver', 30);
            $table->string('status', 20)->default('queued');
            $table->string('provider_id')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'created_at']);
            $table->index(['to', 'created_at']);
        });

        Schema::create('courier_otps', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20)->index();
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('task_pods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('courier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 20); // photo | signature
            $table->string('path');
            $table->decimal('lat', 9, 6)->nullable();
            $table->decimal('lng', 9, 6)->nullable();
            $table->timestamp('taken_at')->nullable();
            $table->timestamps();

            $table->index(['task_id', 'type']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->timestamp('delivery_code_sent_at')->nullable()->after('delivery_code_attempts');
            $table->timestamp('delivery_code_verified_at')->nullable()->after('delivery_code_sent_at');
            $table->string('recipient_name_at_handover')->nullable()->after('delivery_code_verified_at');
        });

        // Offline actions are replayed with a client UUID; remembering it makes replays idempotent.
        Schema::create('courier_action_receipts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('action', 30);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courier_action_receipts');

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['delivery_code_sent_at', 'delivery_code_verified_at', 'recipient_name_at_handover']);
        });

        Schema::dropIfExists('task_pods');
        Schema::dropIfExists('courier_otps');
        Schema::dropIfExists('sms_messages');
    }
};
