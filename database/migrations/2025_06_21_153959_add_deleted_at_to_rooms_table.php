
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->softDeletes(); // Thêm cột deleted_at (kiểu timestamp nullable)
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropSoftDeletes(); // Xóa cột deleted_at nếu rollback
        });
    }
};
