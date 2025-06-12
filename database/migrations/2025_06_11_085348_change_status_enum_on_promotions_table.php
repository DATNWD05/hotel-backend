<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB; // <-- nhớ import

return new class extends Migration
{
    public function up(): void
    {
        // 1) Chuyển mọi 'draft' sang 'scheduled'
        DB::table('promotions')
            ->where('status', 'draft')
            ->update(['status' => 'scheduled']);

        // 2) Giờ mới thay đổi enum, vì không còn giá trị draft
        Schema::table('promotions', function (Blueprint $table) {
            $table->enum('status', [
                    'scheduled',
                    'active',
                    'expired',
                    'cancelled',
                    'depleted',
                ])
                ->default('scheduled')
                ->comment('Luồng trạng thái của chương trình khuyến mãi')
                ->change();
        });
    }

    public function down(): void
    {
        // Ngược lại: khôi phục enum có draft (nếu rollback)
        Schema::table('promotions', function (Blueprint $table) {
            $table->enum('status', [
                    'draft',
                    'scheduled',
                    'active',
                    'expired',
                    'cancelled',
                    'depleted',
                ])
                ->default('draft')
                ->comment('Luồng trạng thái của chương trình khuyến mãi')
                ->change();
        });
    }
};
