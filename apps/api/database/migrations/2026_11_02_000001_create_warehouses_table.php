<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191);
            $table->string('code', 50)->unique();
            $table->string('address_line_1', 191)->nullable();
            $table->string('address_line_2', 191)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('postal_code', 30)->nullable();
            $table->string('country', 2)->default('BD');
            $table->string('phone', 30)->nullable();
            $table->string('email', 191)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('priority')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'priority'], 'warehouses_active_priority_idx');
            $table->index('is_default', 'warehouses_is_default_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};

