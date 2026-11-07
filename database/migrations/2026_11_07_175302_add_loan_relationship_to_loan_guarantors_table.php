<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_guarantors', function (Blueprint $table) {
            $table->string('relationship', 30)->nullable()->after('guarantor_id');
            $table->string('custom_relationship', 100)->nullable()->after('relationship');
        });

        DB::statement('
            UPDATE loan_guarantors
            SET relationship = (
                SELECT guarantors.relationship
                FROM guarantors
                WHERE guarantors.id = loan_guarantors.guarantor_id
            ),
            custom_relationship = (
                SELECT guarantors.custom_relationship
                FROM guarantors
                WHERE guarantors.id = loan_guarantors.guarantor_id
            )
            WHERE relationship IS NULL
        ');
    }

    public function down(): void
    {
        Schema::table('loan_guarantors', function (Blueprint $table) {
            $table->dropColumn(['relationship', 'custom_relationship']);
        });
    }
};
