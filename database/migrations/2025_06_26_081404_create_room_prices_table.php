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
        Schema::create('room_prices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('room_type_id'); // Liên kết với bảng room_types
            $table->string('name'); // Tên mức giá (ví dụ: Tiêu chuẩn, Cuối tuần)
            $table->decimal('price', 15, 2); // Giá phòng
            $table->string('currency')->default('VND'); // Tiền tệ
            $table->enum('status', ['active', 'inactive'])->default('active'); // Trạng thái
            $table->boolean('default')->default(false); // Mức giá mặc định
            $table->timestamps();

            // Khóa ngoại liên kết với bảng room_types
            $table->foreign('room_type_id')->references('id')->on('room_types')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room_prices');
    }
};
