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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->constrained('users', 'uuid')->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 15, 2)->default(0.00);
            $table->decimal('compare_at_price', 15, 2)->nullable();
            $table->string('category')->nullable();
            $table->enum('status', ['active', 'draft'])->default('active');
            $table->string('sku')->nullable();
            $table->integer('initial_stock')->default(0);
            $table->json('media')->nullable(); // Store image paths/URLs
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
