<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Xóa khóa ngoại room_id nếu có
            if (Schema::hasColumn('bookings', 'room_id')) {
                $table->dropForeign(['room_id']);
                $table->dropColumn('room_id');
            }

            // (Tùy chọn) Thêm trường ghi chú đơn đặt phòng
            $table->text('note')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Khôi phục lại cột room_id
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();

            $table->dropColumn('note');
        });
    }
};
