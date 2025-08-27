<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            // Đổi kiểu dữ liệu từ int sang decimal(5,2)
            $table->decimal('worked_hours', 5, 2)->default(0)->change();
            $table->decimal('overtime_hours', 5, 2)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            // Nếu rollback thì đổi lại về integer
            $table->integer('worked_hours')->default(0)->change();
            $table->integer('overtime_hours')->default(0)->change();
        });
    }
};
