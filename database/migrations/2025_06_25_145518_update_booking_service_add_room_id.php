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
        Schema::table('booking_service', function (Blueprint $table) {
            if (!Schema::hasColumn('booking_service', 'room_id')) {
                $table->foreignId('room_id')
                    ->after('booking_id')
                    ->nullable()
                    ->constrained('rooms')
                    ->cascadeOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_service', function (Blueprint $table) {
            // Phải drop foreign key trước
            $table->dropForeign(['room_id']);
            $table->dropColumn('room_id');
        });
    }
};
