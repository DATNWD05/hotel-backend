<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('booking_promotions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('booking_id')
                ->constrained('bookings')
                ->cascadeOnDelete()
                ->comment('FK → bookings');

            $table->foreignId('promotion_id')
                ->constrained('promotions')
                ->comment('FK → promotions');

            $table->string('promotion_code')
                ->comment('Mã khuyến mãi đã áp dụng');

            $table->dateTime('applied_at')
                ->comment('Thời gian áp dụng');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_promotions');
    }
};
