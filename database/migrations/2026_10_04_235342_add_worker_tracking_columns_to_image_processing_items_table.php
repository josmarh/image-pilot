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
        Schema::table('image_processing_items', function (Blueprint $table) {
            $table->uuid('claim_token')->nullable()->index();
            $table->timestamp('claimed_at')->nullable()->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('available_at')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('image_processing_items', function (Blueprint $table) {
            $table->dropColumn([
                'claim_token',
                'claimed_at',
                'attempts',
                'available_at',
            ]);
        });
    }
};
