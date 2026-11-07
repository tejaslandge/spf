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
        Schema::table('foreclosure_requests', function (Blueprint $table) {
            $table->decimal('waived_interest', 15, 2)->default(0)->after('eligible_adjustments');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('foreclosure_requests', function (Blueprint $table) {
            $table->dropColumn('waived_interest');
        });
    }
};
