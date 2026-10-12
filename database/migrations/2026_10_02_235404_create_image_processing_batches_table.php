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
        Schema::create('image_processing_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->uuid('batch_reference')->unique();

            $table->unsignedInteger('original_count')->default(0);
            $table->unsignedInteger('successful_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);

            $table->unsignedBigInteger('original_size_bytes')->default(0);
            $table->unsignedBigInteger('optimized_size_bytes')->default(0);

            $table->string('status', 30)->default('pending');

            $table->json('settings');

            $table->timestamp('expires_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('image_processing_batches');
    }
};
