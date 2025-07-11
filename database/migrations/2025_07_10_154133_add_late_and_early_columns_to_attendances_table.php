<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLateAndEarlyColumnsToAttendancesTable extends Migration
{
    public function up()
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->integer('late_minutes')->nullable()->after('worked_hours')->comment('Số phút đến muộn');
            $table->integer('early_leave_minutes')->nullable()->after('late_minutes')->comment('Số phút về sớm');
        });
    }

    public function down()
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn(['late_minutes', 'early_leave_minutes']);
        });
    }
}
