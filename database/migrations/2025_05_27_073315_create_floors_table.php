<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFloorsTable extends Migration
{
    public function up()
    {
        Schema::create('floors', function (Blueprint $table) {
            $table->id();
            $table->string('name');        // Tên tầng (Tầng 1, Tầng 2, Tầng 3, ...)
            $table->integer('number');     // Số tầng (1, 2, 3, ...)
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('floors');
    }
}

