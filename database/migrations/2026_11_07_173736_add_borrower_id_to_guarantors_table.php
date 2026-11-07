<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guarantors', function (Blueprint $table) {
            $table->foreignId('borrower_id')->nullable()->after('id')->constrained('borrowers')->nullOnDelete();
            $table->unique('borrower_id');
        });
    }

    public function down(): void
    {
        Schema::table('guarantors', function (Blueprint $table) {
            $table->dropConstrainedForeignId('borrower_id');
        });
    }
};
