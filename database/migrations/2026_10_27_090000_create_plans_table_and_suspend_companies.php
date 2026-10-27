<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Plans move from code to the database so the platform admin can change prices, limits and
 * features. The rows below are the plans that used to be hard-coded in App\Support\Plans.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 32)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price_monthly', 10, 2)->nullable();
            $table->decimal('price_yearly', 10, 2)->nullable();
            $table->unsignedInteger('history_days')->nullable();
            $table->unsignedInteger('max_couriers')->nullable();
            $table->jsonb('features')->default('[]');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        $start = ['route_optimization', 'live_tracking', 'status_notifications', 'spreadsheet_import', 'api', 'webhooks'];
        $scale = [...$start, 'pod', 'barcode', 'eta_notifications', 'completion_rules', 'order_batching', 'customer_feedback'];
        $enterprise = [...$scale, 'advanced_optimization', 'multi_brand', 'white_label_tracking', 'sso'];

        $now = now();
        DB::table('plans')->insert([
            ['slug' => 'start', 'name' => 'Start', 'price_monthly' => 35, 'price_yearly' => 30, 'history_days' => 90, 'features' => json_encode($start), 'sort' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'scale', 'name' => 'Scale', 'price_monthly' => 45, 'price_yearly' => 39, 'history_days' => 365, 'features' => json_encode($scale), 'sort' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'enterprise', 'name' => 'Enterprise', 'price_monthly' => null, 'price_yearly' => null, 'history_days' => null, 'features' => json_encode($enterprise), 'sort' => 3, 'created_at' => $now, 'updated_at' => $now],
        ]);

        Schema::table('companies', function (Blueprint $table) {
            $table->timestamp('suspended_at')->nullable()->after('subscription_ends_at');
            $table->text('admin_note')->nullable()->after('suspended_at');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['suspended_at', 'admin_note']);
        });

        Schema::dropIfExists('plans');
    }
};
