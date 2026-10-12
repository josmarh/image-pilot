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
        Schema::create('image_presets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
            ->nullable()
            ->constrained()
            ->nullOnDelete();

            $table->string('name');
            $table->text('description')->nullable();

            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();

            $table->string('resize_mode', 20)->default('contain');
            $table->string('output_format', 10)->default('webp');

            $table->unsignedTinyInteger('quality')->default(80);

            $table->boolean('remove_metadata')->default(true);
            $table->boolean('watermark_enabled')->default(false);

            $table->string('watermark_type', 20)->nullable();
            $table->json('watermark_settings')->nullable();

            $table->boolean('is_system')->default(false);

            $table->timestamps();

            $table->index(['user_id', 'is_system']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('image_presets');
    }
};
