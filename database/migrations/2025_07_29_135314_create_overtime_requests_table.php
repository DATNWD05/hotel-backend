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
            $table->date('work_date'); // ngày làm thêm
            $table->time('start_time'); // giờ bắt đầu tăng ca
            $table->time('end_time');   // giờ kết thúc tăng ca
            $table->text('reason')->nullable(); // lý do tăng ca
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_requests');
    }
};
