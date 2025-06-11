<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            // 1) Thêm cột status nếu chưa có
            if (!Schema::hasColumn('promotions', 'status')) {
                $table->enum('status', [
                    'draft',      // đang soạn thảo
                    'scheduled',  // đã lên lịch, chưa chạy
                    'active',     // đang chạy
                    'expired',    // quá ngày end_date
                    'cancelled',  // hủy thủ công
                    'depleted',   // hết lượt dùng
                ])
                    ->after('used_count')
                    ->default('draft')
                    ->comment('Luồng trạng thái chương trình khuyến mãi');
            }

            // 2) Chuyển default của is_active thành false
            $table->boolean('is_active')
                ->default(false)
                ->comment('Cho biết khuyến mãi có đang áp dụng hay không')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            if (Schema::hasColumn('promotions', 'status')) {
                $table->dropColumn('status');
            }
            $table->boolean('is_active')
                ->default(true)
                ->change();
        });
    }
};
