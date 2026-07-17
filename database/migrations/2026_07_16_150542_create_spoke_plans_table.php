<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spoke_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spoke_id')->constrained('spokes')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->unsignedInteger('price_cents')->nullable()->comment('Price in cents for future Stripe integration');
            $table->json('features')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['spoke_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spoke_plans');
    }
};
