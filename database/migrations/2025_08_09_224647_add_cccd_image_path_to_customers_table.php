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
        Schema::table('customers', function (Blueprint $table) {
            $table->string('cccd_image_path', 255)
                  ->nullable()
                  ->after('cccd')
                  ->comment('Đường dẫn lưu ảnh CCCD trong storage/private');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('cccd_image_path');
        });
    }
};
