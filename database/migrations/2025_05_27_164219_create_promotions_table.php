<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique()->comment('Mã CTKM');
            $table->text('description')->nullable()->comment('Mô tả chi tiết');
            $table->enum('discount_type', ['percent', 'amount'])->comment('Loại giảm');
            $table->decimal('discount_value', 10, 2)->comment('Giá trị giảm');
            $table->date('start_date')->comment('Ngày bắt đầu');
            $table->date('end_date')->comment('Ngày kết thúc');
            $table->integer('usage_limit')->default(1)->comment('Giới hạn số lần dùng');
            $table->integer('used_count')->default(0)->comment('Số lần đã dùng');
            $table->boolean('is_active')->default(true)->comment('Cho biết promotion còn hiệu lực (hiển thị) hay đã ẩn');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
