<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Xóa foreign key floor_id nếu tồn tại
        $fk = DB::select("SELECT CONSTRAINT_NAME
                          FROM information_schema.KEY_COLUMN_USAGE
                          WHERE TABLE_SCHEMA = DATABASE()
                          AND TABLE_NAME = 'rooms'
                          AND COLUMN_NAME = 'floor_id'
                          AND REFERENCED_TABLE_NAME IS NOT NULL");

        if (!empty($fk)) {
            $constraint = $fk[0]->CONSTRAINT_NAME;
            DB::statement("ALTER TABLE `rooms` DROP FOREIGN KEY `$constraint`");
        }

        // 2. Xóa cột floor_id nếu tồn tại
        if (Schema::hasColumn('rooms', 'floor_id')) {
            Schema::table('rooms', function (Blueprint $table) {
                $table->dropColumn('floor_id');
            });
        }

        // 3. Cập nhật khóa room_type_id
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropForeign(['room_type_id']);
            $table->foreign('room_type_id')
                ->references('id')->on('room_types')
                ->restrictOnDelete();
        });

        // 4. Chỉ thêm unique nếu chưa có
        $uniqueExists = DB::select("SELECT COUNT(1) AS count
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'rooms'
              AND INDEX_NAME = 'rooms_room_number_unique'");

        if (empty($uniqueExists) || $uniqueExists[0]->count == 0) {
            Schema::table('rooms', function (Blueprint $table) {
                $table->unique('room_number');
            });
        }
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->foreignId('floor_id')->nullable()->constrained()->nullOnDelete();

            try {
                $table->dropUnique(['room_number']);
            } catch (\Throwable $e) {}

            $table->dropForeign(['room_type_id']);
            $table->foreignId('room_type_id')->nullable()->constrained()->nullOnDelete()->change();
        });
    }
};
