<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Đổi từ date -> datetime
            $table->dateTime('check_in_date')->change();
            $table->dateTime('check_out_date')->change();

            // Thêm trường is_hourly
            $table->boolean('is_hourly')->default(false)->after('check_out_date');
        });
    }

    public function down()
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Trả về lại date
            $table->date('check_in_date')->change();
            $table->date('check_out_date')->change();

            // Xoá cột is_hourly
            $table->dropColumn('is_hourly');
        });
    }
};
