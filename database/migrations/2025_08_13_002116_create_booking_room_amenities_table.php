<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('booking_room_amenities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('amenity_id')->constrained()->cascadeOnDelete();

            // Giá lưu kiểu nguyên VND cho an toàn (có thể dùng decimal nếu bạn muốn)
            $table->unsignedBigInteger('price')->default(0);
            $table->unsignedInteger('quantity')->default(1);

            $table->timestamps();

            // Mỗi tiện nghi theo 1 phòng trong 1 booking chỉ 1 dòng
            $table->unique(['booking_id', 'room_id', 'amenity_id']);
            $table->index(['booking_id', 'room_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_room_amenities');
    }
};
