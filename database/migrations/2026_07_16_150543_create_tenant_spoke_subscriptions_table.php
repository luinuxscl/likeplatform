<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_spoke_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('spoke_id')->constrained('spokes')->cascadeOnDelete();
            $table->foreignId('spoke_plan_id')->nullable()->constrained('spoke_plans')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamp('subscribed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'spoke_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_spoke_subscriptions');
    }
};
