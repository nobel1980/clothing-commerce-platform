<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name', 191);
            $table->string('slug', 191)->unique();
            $table->text('description')->nullable();
            $table->longText('body')->nullable(); // rich content

            // Publication
            $table->string('status', 30)->default('draft'); // draft, published, archived
            $table->boolean('is_featured')->default(false);
            $table->timestamp('published_at')->nullable();

            // SEO
            $table->string('meta_title', 191)->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->string('meta_keywords', 500)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('slug');
            $table->index('brand_id');
            $table->index('category_id');
            $table->index('status');
            $table->index('is_featured');
            $table->index(['status', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
