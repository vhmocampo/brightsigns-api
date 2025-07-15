<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_estimate_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_estimate_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_price', 10, 2);
            $table->string('product_id')->nullable();
            $table->decimal('similarity_score', 3, 2)->nullable();
            $table->timestamps();

            $table->index(['quote_estimate_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_estimate_line_items');
    }
};
