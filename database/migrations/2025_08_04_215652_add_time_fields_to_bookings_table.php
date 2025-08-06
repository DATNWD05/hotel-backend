<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTimeFieldsToBookingsTable extends Migration
{
    public function up()
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Thêm cột cho thời gian nhận phòng
            $table->time('check_in_time')->nullable()->after('check_out_at');

            // Thêm cột cho thời gian trả phòng
            $table->time('check_out_time')->nullable()->after('check_in_time');
        });
    }

    public function down()
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Xóa các cột khi rollback
            $table->dropColumn(['check_in_time', 'check_out_time']);
        });
    }
}
