<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $column = DB::selectOne(
            'SELECT IS_NULLABLE
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?',
            ['image_processing_batches', 'user_id']
        );

        if ($column === null) {
            throw new \RuntimeException(
                'The image_processing_batches.user_id column was not found.'
            );
        }

        if (strtoupper($column->IS_NULLABLE) === 'YES') {
            return;
        }

        Schema::table('image_processing_batches', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->unsignedBigInteger('user_id')->nullable()->change();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $guestBatchesExist = DB::table('image_processing_batches')
            ->whereNull('user_id')
            ->exists();

        if ($guestBatchesExist) {
            throw new \RuntimeException(
                'Cannot reverse this migration while guest batches exist.'
            );
        }

        $column = DB::selectOne(
            'SELECT IS_NULLABLE
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?',
            ['image_processing_batches', 'user_id']
        );

        if ($column === null || strtoupper($column->IS_NULLABLE) === 'NO') {
            return;
        }

        Schema::table('image_processing_batches', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->unsignedBigInteger('user_id')->nullable(false)->change();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }
};
