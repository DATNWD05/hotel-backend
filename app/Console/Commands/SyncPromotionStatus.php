<?php

namespace App\Console\Commands;

use App\Models\Promotion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncPromotionStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-promotion-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Đồng bộ status và is_active cho tất cả promotions';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            // Lấy các promotion cần đồng bộ (loại trừ trạng thái cuối)
            $promotions = Promotion::whereNotIn('status', ['cancelled', 'expired', 'depleted'])
                ->chunkById(100, function ($promotions) {
                    $updatedCount = 0;
                    foreach ($promotions as $promotion) {
                        $promotion->syncStatus();
                        if ($promotion->wasChanged(['status', 'is_active'])) {
                            $updatedCount++;
                        }
                    }
                    $this->info("Đã đồng bộ {$updatedCount} bản ghi trong batch này.");
                });

            $this->info('Đã hoàn tất đồng bộ trạng thái cho promotions.');
            Log::info('Promotion status sync completed successfully.');
        } catch (\Throwable $e) {
            Log::error("Lỗi khi đồng bộ trạng thái promotions: {$e->getMessage()}");
            $this->error('Có lỗi xảy ra trong quá trình đồng bộ. Vui lòng kiểm tra log.');
            throw $e; // Ném lại ngoại lệ để Laravel xử lý (tùy chọn)
        }
    }
}
