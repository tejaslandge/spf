<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->string('funding_status', 20)->default('unfunded')->index()->after('status');
            $table->timestamp('funding_locked_at')->nullable()->after('funding_status');
        });

        // Loans already disbursed or closed were funded in substance; mark them so the gate keeps them disbursable in history.
        DB::table('loans')
            ->whereIn('status', ['active', 'closed'])
            ->update(['funding_status' => 'fully_funded', 'funding_locked_at' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn(['funding_status', 'funding_locked_at']);
        });
    }
};
