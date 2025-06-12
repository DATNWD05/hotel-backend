<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_type_amenities', function (Blueprint $table) {
            // FK → room_types.id
            $table->unsignedBigInteger('room_type_id')->comment('FK → room_types.id');
            $table->unsignedBigInteger('amenity_id')->comment('FK → amenities.id');

            // Số lượng tiện nghi có trong loại phòng
            $table->integer('quantity')
                ->default(1)
                ->comment('Số lượng tiện nghi có trong loại phòng này');

            // Composite primary key
            $table->primary(['room_type_id', 'amenity_id']);

            // Khai báo khóa ngoại rõ ràng
            $table->foreign('room_type_id')
                ->references('id')
                ->on('room_types')
                ->onDelete('cascade');

            $table->foreign('amenity_id')
                ->references('id')
                ->on('amenities')
                ->onDelete('cascade');
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('room_type_amenities');
    }
};
