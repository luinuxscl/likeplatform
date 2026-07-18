<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spoke_plans', function (Blueprint $table): void {
            $table->string('stripe_product_id')->nullable()->after('features');
            $table->string('stripe_price_id')->nullable()->after('stripe_product_id');
        });
    }

    public function down(): void
    {
        Schema::table('spoke_plans', function (Blueprint $table): void {
            $table->dropColumn(['stripe_product_id', 'stripe_price_id']);
        });
    }
};
