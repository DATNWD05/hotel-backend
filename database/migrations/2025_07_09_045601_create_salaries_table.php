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
        Schema::create('salaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->integer('month'); // tháng tính lương
            $table->integer('year');
            $table->integer('total_hours')->default(0);
            $table->integer('total_salary')->default(0);
            $table->timestamps();

            $table->unique(['employee_id', 'month', 'year']); // tránh bị trùng
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salaries');
    }
};
