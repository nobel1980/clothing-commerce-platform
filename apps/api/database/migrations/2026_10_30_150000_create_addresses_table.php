<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone');
            $table->string('division');       // Bangladesh Divisions: Dhaka, Chattogram, etc.
            $table->string('district');       // District
            $table->string('upazila_thana');  // Upazila / Thana
            $table->text('street_address');   // House, Road, Area
            $table->string('postal_code')->nullable();
            $table->string('type')->default('shipping'); // 'shipping', 'billing', 'both'
            $table->boolean('is_default')->default(false);
            $table->text('delivery_instructions')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_default']);
            $table->index(['user_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
