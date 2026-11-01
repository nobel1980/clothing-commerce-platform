<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('disk', 30)->default('public'); // storage abstraction: 'public', 's3', etc.
            $table->string('path', 500);                   // relative path on disk
            $table->string('url', 500)->nullable();        // cached public URL
            $table->string('alt_text', 191)->nullable();
            $table->integer('sort_order')->default(0);     // ordering support
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index(['product_id', 'sort_order']);
            $table->index(['product_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
