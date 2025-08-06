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
        Schema::table('room_types', function (Blueprint $table) {
            $table->decimal('hourly_rate', 10, 2)->nullable()->after('base_rate');
        });
    }

    public function down()
    {
        Schema::table('room_types', function (Blueprint $table) {
            $table->dropColumn('hourly_rate');
        });
    }
};
