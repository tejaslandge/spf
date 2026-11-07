<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emi_schedules', function (Blueprint $table): void {
            $table->string('settlement_type', 30)->nullable()->after('status')->index();
        });
    }

    public function down(): void
    {
        Schema::table('emi_schedules', function (Blueprint $table): void {
            $table->dropColumn('settlement_type');
        });
    }
};
