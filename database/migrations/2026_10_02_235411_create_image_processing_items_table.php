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
        Schema::create('image_processing_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')
                ->constrained('image_processing_batches')
                ->cascadeOnDelete();

            $table->string('original_filename');
            $table->string('original_path');
            $table->unsignedBigInteger('original_size_bytes');

            $table->unsignedInteger('original_width')->nullable();
            $table->unsignedInteger('original_height')->nullable();
            $table->string('original_format', 10)->nullable();

            $table->string('output_filename')->nullable();
            $table->string('output_path')->nullable();

            $table->unsignedBigInteger('output_size_bytes')->nullable();

            $table->unsignedInteger('output_width')->nullable();
            $table->unsignedInteger('output_height')->nullable();
            $table->string('output_format', 10)->nullable();

            $table->string('status', 30)->default('pending');

            $table->text('error_message')->nullable();

            $table->timestamps();

            $table->index(['batch_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('image_processing_items');
    }
};
