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
            $table->string('room_number');      // Số phòng (101, 102, ...)
            $table->foreignId('room_type_id')->constrained()->onDelete('cascade');  // Liên kết với bảng room_types
            $table->foreignId('floor_id')->constrained()->onDelete('cascade');     // Liên kết với bảng floors
            $table->decimal('price', 10, 2);    // Giá phòng
            $table->enum('status', ['available', 'booked', 'cleaning', 'maintenance'])->default('available');  // Trạng thái phòng
            $table->string('image')->nullable();  // Thêm trường `image` với kiểu dữ liệu string để lưu đường dẫn ảnh
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('rooms');
    }
}
