<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::defaultStringLength(191);

        Schema::create('product_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('order_id')
                ->nullable()
                ->constrained('orders')
                ->nullOnDelete();
            $table->string('reviewer_name', 191);
            $table->string('reviewer_email', 191)->nullable();
            $table->unsignedTinyInteger('rating'); // 1..5
            $table->string('title', 191)->nullable();
            $table->text('comment');
            $table->string('status', 40)->default('pending'); // pending, approved, rejected
            $table->boolean('is_verified_purchase')->default(false);
            $table->foreignId('moderated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();
            $table->string('moderation_notes', 500)->nullable();
            $table->timestamps();

            $table->index(['product_id', 'status']);
            $table->index(['status', 'rating']);
            $table->index('user_id');
            $table->index('order_id');
        });

        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();
            $table->string('group', 60)->index();
            $table->string('key', 120)->unique();
            $table->json('value')->nullable();
            $table->string('type', 40)->default('string'); // string, integer, boolean, json
            $table->string('label', 191);
            $table->string('description', 500)->nullable();
            $table->boolean('is_public')->default(false)->index();
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
        Schema::dropIfExists('product_reviews');
    }
};

