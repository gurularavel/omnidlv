<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Native courier app: devices for push, deletion requests, GPS fix time and mock flag. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courier_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('token_id')->nullable()->index(); // personal_access_tokens.id
            $table->string('platform', 16);                                // android, ios
            $table->string('push_token', 512)->nullable()->unique();
            $table->string('app_version', 32)->nullable();
            $table->string('os_version', 64)->nullable();
            $table->string('model', 128)->nullable();
            $table->string('locale', 8)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('account_deletion_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason', 1000)->nullable();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
        });

        Schema::table('courier_locations', function (Blueprint $table) {
            // When the phone took the fix (last_seen_at is when the server last heard from it).
            $table->timestamp('fixed_at')->nullable()->after('battery');
            $table->boolean('is_mock')->default(false)->after('fixed_at');
        });

        Schema::table('courier_location_pings', function (Blueprint $table) {
            $table->unsignedTinyInteger('battery')->nullable()->after('accuracy');
            $table->boolean('is_mock')->default(false)->after('battery');
        });
    }

    public function down(): void
    {
        Schema::table('courier_location_pings', fn (Blueprint $table) => $table->dropColumn(['battery', 'is_mock']));
        Schema::table('courier_locations', fn (Blueprint $table) => $table->dropColumn(['fixed_at', 'is_mock']));
        Schema::dropIfExists('account_deletion_requests');
        Schema::dropIfExists('courier_devices');
    }
};
