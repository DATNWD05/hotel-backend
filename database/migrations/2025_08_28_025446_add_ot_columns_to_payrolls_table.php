<?php
// database/migrations/xxxx_xx_xx_xxxxxx_add_ot_columns_to_payrolls_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            // giờ OT (ưu tiên decimal để giữ lẻ)
            $table->decimal('overtime_hours', 5, 2)->default(0)->after('total_hours');
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn('overtime_hours');
        });
    }
};
