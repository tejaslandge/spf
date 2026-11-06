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
            $table->string('application_status', 30)->default('submitted')->index()->after('status');
            $table->string('purpose')->nullable()->after('application_status');
            $table->decimal('monthly_income', 15, 2)->nullable()->after('purpose');
            $table->string('employment_details')->nullable()->after('monthly_income');
            $table->decimal('processing_fee', 15, 2)->default(0)->after('foreclosure_charge');
            $table->decimal('other_charges', 15, 2)->default(0)->after('processing_fee');
            $table->timestamp('capital_verified_at')->nullable()->after('funding_locked_at');
            $table->foreignId('capital_verified_by')->nullable()->constrained('users')->nullOnDelete()->after('capital_verified_at');
            $table->timestamp('final_approved_at')->nullable()->after('capital_verified_by');
            $table->foreignId('final_approved_by')->nullable()->constrained('users')->nullOnDelete()->after('final_approved_at');
            $table->string('disbursement_reference')->nullable()->after('disbursement_date');
        });

        // Loans created before workflow tracking existed have already passed these gates in substance.
        DB::table('loans')->update([
            'application_status' => 'accepted',
            'capital_verified_at' => DB::raw('created_at'),
            'final_approved_at' => DB::raw('COALESCE(disbursement_date, created_at)'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn([
                'application_status',
                'purpose',
                'monthly_income',
                'employment_details',
                'processing_fee',
                'other_charges',
                'capital_verified_at',
                'capital_verified_by',
                'final_approved_at',
                'final_approved_by',
                'disbursement_reference',
            ]);
        });
    }
};
