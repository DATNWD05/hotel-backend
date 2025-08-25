<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('salary_rules', function (Blueprint $table) {
            $table->decimal('hourly_rate', 12, 2)
                  ->nullable()
                  ->after('overtime_multiplier');
        });
    }

    public function down(): void
    {
        Schema::table('salary_rules', function (Blueprint $table) {
            $table->dropColumn('hourly_rate');
        });
    }
};
