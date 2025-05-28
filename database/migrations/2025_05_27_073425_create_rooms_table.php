<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRoomsTable extends Migration
{
    public function up()
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('room_number'); // Số phòng (101, 102, ...)

            // Khóa ngoại có thể null
            $table->foreignId('room_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('floor_id')->nullable()->constrained()->nullOnDelete();

            $table->decimal('price', 10, 2); // Giá phòng
            $table->enum('status', ['available', 'booked', 'cleaning', 'maintenance'])->default('available'); // Trạng thái phòng
            $table->string('image')->nullable(); // Đường dẫn ảnh
            $table->timestamps();
        });
    }


    public function down()
    {
        Schema::dropIfExists('rooms');
    }
}
