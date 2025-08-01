<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('overtime_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->date('work_date'); // Ngày làm thêm
            $table->datetime('start_datetime')->nullable(); // Thời gian bắt đầu tăng ca đầy đủ
            $table->datetime('end_datetime')->nullable();   // Thời gian kết thúc tăng ca đầy đủ
            $table->text('reason')->nullable();            // Lý do tăng ca
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_requests');
    }
};
