<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investor_payouts', function (Blueprint $table) {
            $table->unsignedInteger('batch_no')->default(1)->after('period_month');
            $table->timestamp('approved_at')->nullable()->after('status');
            $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable()->after('approved_by');
            $table->foreignId('rejected_by')->nullable()->after('rejected_at')->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable()->after('rejected_by');
            $table->timestamp('processed_at')->nullable()->after('paid_at');
            $table->foreignId('processed_by')->nullable()->after('processed_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('investor_payouts', function (Blueprint $table) {
            $table->dropUnique(['investor_id', 'period_month']);
            $table->unique(['investor_id', 'period_month', 'batch_no']);
        });

        Schema::table('emi_payments', function (Blueprint $table) {
            $table->boolean('is_reversed')->default(false)->index()->after('penalty_amount');
            $table->timestamp('reversed_at')->nullable()->after('is_reversed');
            $table->foreignId('reversed_by')->nullable()->after('reversed_at')->constrained('users')->nullOnDelete();
            $table->text('reversal_reason')->nullable()->after('reversed_by');
            $table->foreignId('reversal_of_payment_id')->nullable()->after('reversal_reason')->constrained('emi_payments')->nullOnDelete();
        });

        Schema::create('emi_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emi_payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('emi_schedule_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->decimal('principal_amount', 15, 2);
            $table->decimal('interest_amount', 15, 2);
            $table->timestamps();

            $table->unique(['emi_payment_id', 'emi_schedule_id']);
        });

        $accounts = [
            ['code' => '2200', 'name' => 'Investor Interest Payable', 'type' => 'liability', 'is_system' => true, 'is_active' => true],
            ['code' => '2300', 'name' => 'Admin Commission Payable', 'type' => 'liability', 'is_system' => true, 'is_active' => true],
            ['code' => '4300', 'name' => 'Company Interest Share', 'type' => 'income', 'is_system' => true, 'is_active' => true],
        ];

        foreach ($accounts as $account) {
            if (! DB::table('accounts')->where('code', $account['code'])->exists()) {
                DB::table('accounts')->insert($account + ['created_at' => now(), 'updated_at' => now()]);
            }
        }

        if (! DB::table('interest_configurations')->exists()) {
            DB::table('interest_configurations')->insert([
                'name' => 'Default monthly interest distribution',
                'effective_from' => now()->toDateString(),
                'borrower_monthly_rate' => 1.500000,
                'default_investor_monthly_rate' => 1.500000,
                'investor_allocation_percent' => 60.0000,
                'admin_allocation_percent' => 10.0000,
                'company_allocation_percent' => 30.0000,
                'default_admin_commission_rate' => 10.0000,
                'is_active' => true,
                'notes' => 'Bootstrap configuration. Review before production use.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('emi_payment_allocations');

        Schema::table('emi_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reversal_of_payment_id');
            $table->dropConstrainedForeignId('reversed_by');
            $table->dropColumn(['is_reversed', 'reversed_at', 'reversal_reason']);
        });

        Schema::table('investor_payouts', function (Blueprint $table) {
            $table->dropUnique(['investor_id', 'period_month', 'batch_no']);
            $table->dropColumn('batch_no');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropConstrainedForeignId('rejected_by');
            $table->dropConstrainedForeignId('processed_by');
            $table->dropColumn(['approved_at', 'rejected_at', 'rejection_reason', 'processed_at']);
            $table->unique(['investor_id', 'period_month']);
        });

        DB::table('accounts')->whereIn('code', ['2200', '2300', '4300'])->delete();
    }
};
