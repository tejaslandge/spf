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
        Schema::table('borrowers', function (Blueprint $table) {
            $table->string('bank_name')->nullable()->after('emergency_contact_number');
            $table->string('bank_account_number', 40)->nullable()->after('bank_name');
            $table->string('bank_ifsc', 20)->nullable()->after('bank_account_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('borrowers', function (Blueprint $table) {
            $table->dropColumn(['bank_name', 'bank_account_number', 'bank_ifsc']);
        });
    }
};
