<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('attendances', function (Blueprint $table) {
            // Cho phép ca OT độc lập
            $table->unsignedBigInteger('shift_id')->nullable()->change();

            // Gắn bản ghi attendance với 1 đăng ký OT cụ thể (custom)
            if (!Schema::hasColumn('attendances', 'overtime_request_id')) {
                $table->unsignedBigInteger('overtime_request_id')->nullable()->after('shift_id');
                $table->foreign('overtime_request_id')
                      ->references('id')->on('overtime_requests')
                      ->nullOnDelete();
            }

            if (!Schema::hasColumn('attendances', 'is_overtime')) {
                $table->boolean('is_overtime')->default(false)->after('overtime_request_id');
            }

            // Tăng tốc truy vấn theo “slot”
            $table->index(['employee_id', 'work_date', 'shift_id', 'overtime_request_id'], 'att_by_slot');
        });
    }

    public function down(): void {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex('att_by_slot');

            if (Schema::hasColumn('attendances', 'is_overtime')) {
                $table->dropColumn('is_overtime');
            }
            if (Schema::hasColumn('attendances', 'overtime_request_id')) {
                $table->dropForeign(['overtime_request_id']);
                $table->dropColumn('overtime_request_id');
            }

            // Nếu down() mà cột đang chứa NULL, bạn cần dọn dữ liệu trước khi đổi lại NOT NULL
            // $table->unsignedBigInteger('shift_id')->nullable(false)->change();
        });
    }
};
