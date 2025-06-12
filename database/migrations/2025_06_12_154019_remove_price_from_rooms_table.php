<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemovePriceFromRoomsTable extends Migration
{
    public function up()
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn('price');
        });
    }

    public function down()
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->after('floor_id');
        });
    }
}
