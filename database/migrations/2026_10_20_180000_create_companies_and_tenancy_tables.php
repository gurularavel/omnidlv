<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('short_name', 32)->nullable();
            $table->string('slug')->unique();
            $table->string('timezone', 64)->default('Asia/Baku');
            $table->string('locale', 5)->default('az');
            $table->string('country', 2)->default('AZ');
            $table->string('currency', 3)->default('AZN');
            $table->string('brand_logo')->nullable();
            $table->string('primary_color', 9)->default('#0f766e');
            $table->string('secondary_color', 9)->default('#1e293b');
            $table->string('plan_slug', 32)->default('start');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('subscription_ends_at')->nullable();
            $table->jsonb('settings')->default('{}');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
            $table->string('phone_country', 2)->nullable()->after('email');
            $table->string('phone', 20)->nullable()->unique()->after('phone_country');
            $table->string('locale', 5)->default('az')->after('phone');
            $table->string('avatar')->nullable();
            $table->boolean('is_super')->default(false);
            $table->foreignId('active_company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->timestamp('last_login_at')->nullable();
            $table->softDeletes();
        });

        Schema::create('employments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role_slug', 20);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'user_id']);
            $table->index(['company_id', 'role_slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employments');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('active_company_id');
            $table->dropColumn(['phone_country', 'phone', 'locale', 'avatar', 'is_super', 'last_login_at', 'deleted_at']);
        });

        Schema::dropIfExists('companies');
    }
};
