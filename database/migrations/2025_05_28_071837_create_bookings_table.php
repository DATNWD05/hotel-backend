<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */ // database/migrations/xxxx_create_bookings_table.php
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')
                ->constrained('customers')
                ->cascadeOnDelete()
                ->comment('Khách hàng');
            $table->foreignId('created_by')
                ->constrained('users')
                ->cascadeOnDelete()
                ->comment('Nhân viên tạo');
            $table->date('check_in_date')->comment('Ngày nhận phòng');
            $table->date('check_out_date')->comment('Ngày trả phòng');
            $table->enum('status', [
                'Pending',
                'Confirmed',
                'Checked-in',
                'Checked-out',
                'Canceled'
            ])->default('Pending')->comment('Trạng thái');
            $table->decimal('deposit_amount', 12, 2)
                ->default(0)
                ->comment('Đặt cọc');
            $table->decimal('raw_total', 12, 2)
                ->default(0)
                ->comment('Tổng gốc');
            $table->decimal('discount_amount', 12, 2)
                ->default(0)
                ->comment('Tổng giảm giá');
            $table->decimal('total_amount', 12, 2)
                ->default(0)
                ->comment('Tổng cuối');
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
