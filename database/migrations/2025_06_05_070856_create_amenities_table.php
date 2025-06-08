<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('amenities', function (Blueprint $table) {
            $table->id();

            // FK → amenity_categories
            $table->foreignId('category_id')
                  ->constrained('amenity_categories')
                  ->cascadeOnDelete()
                  ->comment('FK → amenity_categories');

            $table->string('code', 50)
                  ->unique()
                  ->comment('Mã tiện nghi duy nhất');
            $table->string('name', 100)
                  ->comment('Tên tiện nghi');
            $table->text('description')
                  ->nullable()
                  ->comment('Mô tả tiện nghi');
            $table->string('icon', 255)
                  ->nullable()
                  ->comment('Tên file hoặc đường dẫn icon');

            // Phần mở rộng: price, default_quantity, status
            $table->decimal('price', 10, 2)
                  ->default(0)
                  ->comment('Nếu thiết bị → phí bảo trì; nếu tiêu hao → giá mỗi đơn vị');
            $table->integer('default_quantity')
                  ->default(0)
                  ->comment('Số lượng mặc định cho vật tư tiêu hao');
            $table->enum('status', ['active', 'inactive'])
                  ->default('active')
                  ->comment('Trạng thái tiện nghi');

            $table->timestamps(); // created_at, updated_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amenities');
    }
};
