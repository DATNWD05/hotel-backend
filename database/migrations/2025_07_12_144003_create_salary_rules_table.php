<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('salary_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('role_id')->unique(); // mỗi vai trò 1 rule
            $table->decimal('overtime_multiplier', 4, 2)->default(1.5); // Hệ số tăng ca
            $table->integer('late_penalty_per_minute')->default(1000); // Phạt/phút
            $table->integer('early_leave_penalty_per_minute')->default(1000);
            $table->integer('daily_allowance')->default(0); // Phụ cấp chuyên cần
            $table->timestamps();

            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_rules');
    }
};
