<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('amenity_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)
                ->unique()
                ->comment('Tên nhóm tiện nghi, ví dụ: "Tiện nghi phòng", "Dịch vụ bổ sung"');
            $table->text('description')
                ->nullable()
                ->comment('Mô tả nhóm tiện nghi (nếu cần)');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amenity_categories');
    }
};
