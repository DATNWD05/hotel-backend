<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_type_amenities', function (Blueprint $table) {
            // FK → room_types
            $table->foreignId('room_type_id')
                  ->constrained('room_types')
                  ->cascadeOnDelete()
                  ->comment('FK → room_types');

            // FK → amenities
            $table->foreignId('amenity_id')
                  ->constrained('amenities')
                  ->cascadeOnDelete()
                  ->comment('FK → amenities');

            // Cột quantity
            $table->integer('quantity')
                  ->default(1)
                  ->comment('Số lượng tiện nghi có trong loại phòng này');

            // Composite primary key
            $table->primary(['room_type_id', 'amenity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_type_amenities');
    }
};
