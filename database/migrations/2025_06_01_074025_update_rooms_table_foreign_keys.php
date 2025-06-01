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
        Schema::table('rooms', function (Blueprint $table) {
            // Xóa khóa ngoại và cột floor_id nếu tồn tại
            $table->dropForeign(['floor_id']);
            $table->dropColumn('floor_id');

            // Xóa khóa ngoại room_type_id cũ
            $table->dropForeign(['room_type_id']);

            // Thêm lại room_type_id với restrictOnDelete
            $table->foreign('room_type_id')
                ->references('id')->on('room_types')
                ->restrictOnDelete();

            // Thêm ràng buộc duy nhất cho room_number nếu chưa có
            $table->unique('room_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            // Khôi phục lại floor_id
            $table->foreignId('floor_id')->nullable()->constrained()->nullOnDelete();

            // Xóa unique room_number
            $table->dropUnique(['room_number']);

            // Đổi lại room_type_id
            $table->dropForeign(['room_type_id']);
            $table->foreignId('room_type_id')->nullable()->constrained()->nullOnDelete()->change();
        });
    }
};
