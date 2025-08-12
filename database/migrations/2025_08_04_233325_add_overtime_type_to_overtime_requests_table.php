<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('overtime_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('overtime_requests', 'overtime_type')) {
                $table->enum('overtime_type', ['after_shift', 'custom'])
                    ->default('after_shift')
                    ->after('employee_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('overtime_requests', function (Blueprint $table) {
            if (Schema::hasColumn('overtime_requests', 'overtime_type')) {
                $table->dropColumn('overtime_type');
            }
        });
    }
};
