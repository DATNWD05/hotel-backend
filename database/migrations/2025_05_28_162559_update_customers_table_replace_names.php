<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // 1. Xóa hai cột cũ
            $table->dropColumn(['first_name', 'last_name']);

            // 2. Thêm cột mới
            $table->string('name', 200)
                  ->after('cccd'); // đặt vị trí sau cccd, hoặc adjust theo ý bạn
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // 1. Thêm lại hai cột ban đầu
            $table->string('first_name', 100)
                  ->after('cccd');
            $table->string('last_name', 100)
                  ->after('first_name');    

            // 2. Xóa cột name
            $table->dropColumn('name');
        });
    }
};
