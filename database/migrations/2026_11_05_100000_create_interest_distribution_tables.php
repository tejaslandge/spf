<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interest_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->date('effective_from')->index();
            $table->decimal('borrower_monthly_rate', 9, 6);
            $table->decimal('default_investor_monthly_rate', 9, 6);
            $table->decimal('investor_allocation_percent', 9, 4);
            $table->decimal('admin_allocation_percent', 9, 4);
            $table->decimal('company_allocation_percent', 9, 4);
            $table->decimal('default_admin_commission_rate', 9, 4)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'effective_from']);
        });

        Schema::create('investor_interest_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investor_id')->constrained()->restrictOnDelete();
            $table->decimal('monthly_rate', 9, 6);
            $table->date('effective_from')->index();
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['investor_id', 'effective_from']);
        });

        Schema::create('admin_commission_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->string('designation', 80)->nullable();
            $table->decimal('commission_rate', 9, 4);
            $table->date('effective_from')->index();
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('loan_distribution_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('interest_configuration_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('borrower_monthly_rate', 9, 6);
            $table->decimal('default_investor_monthly_rate', 9, 6);
            $table->decimal('investor_allocation_percent', 9, 4);
            $table->decimal('admin_allocation_percent', 9, 4);
            $table->decimal('company_allocation_percent', 9, 4);
            $table->json('rules_snapshot');
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('loan_investor_distributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->restrictOnDelete();
            $table->foreignId('investor_id')->constrained()->restrictOnDelete();
            $table->foreignId('investor_allocation_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('principal_amount', 15, 2);
            $table->decimal('monthly_rate', 9, 6);
            $table->decimal('share_percent', 9, 6);
            $table->timestamps();

            $table->unique(['loan_id', 'investor_id']);
            $table->index('investor_id');
        });

        Schema::create('loan_admin_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->decimal('commission_rate', 9, 4);
            $table->boolean('is_primary')->default(true);
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();

            $table->unique(['loan_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('interest_distribution_ledgers', function (Blueprint $table) {
            $table->id();
            $table->string('entitlement_key', 191)->unique();
            $table->foreignId('emi_payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('loan_id')->constrained()->restrictOnDelete();
            $table->string('beneficiary_type', 20)->index();
            $table->string('entry_type', 20)->default('earning')->index();
            $table->foreignId('investor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('investor_allocation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('loan_investor_distribution_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 15, 2);
            $table->decimal('collected_interest', 15, 2);
            $table->decimal('applicable_rate', 9, 6)->nullable();
            $table->decimal('share_percent', 9, 6)->nullable();
            $table->date('period_month')->index();
            $table->string('status', 20)->default('earned')->index();
            $table->foreignId('reversal_of_id')->nullable()->unique()->constrained('interest_distribution_ledgers')->nullOnDelete();
            $table->string('reference', 120)->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['emi_payment_id', 'beneficiary_type']);
            $table->index(['investor_id', 'period_month']);
            $table->index(['admin_id', 'period_month']);
        });

        Schema::create('investor_payout_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investor_payout_id')->constrained()->cascadeOnDelete();
            $table->foreignId('distribution_ledger_id')->unique()->constrained('interest_distribution_ledgers')->restrictOnDelete();
            $table->foreignId('loan_id')->constrained()->restrictOnDelete();
            $table->foreignId('investor_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->timestamps();

            $table->index(['investor_payout_id', 'investor_id']);
        });

        Schema::create('admin_commission_payouts', function (Blueprint $table) {
            $table->id();
            $table->string('payout_code', 30)->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->date('period_month');
            $table->unsignedInteger('batch_no')->default(1);
            $table->decimal('gross_commission', 15, 2)->default(0);
            $table->decimal('net_amount', 15, 2)->default(0);
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('payment_reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'period_month', 'batch_no']);
            $table->index(['period_month', 'status']);
        });

        Schema::create('admin_commission_payout_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_commission_payout_id')->constrained()->cascadeOnDelete();
            $table->foreignId('distribution_ledger_id')->unique()->constrained('interest_distribution_ledgers')->restrictOnDelete();
            $table->foreignId('loan_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->timestamps();

            $table->index(['admin_commission_payout_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_commission_payout_items');
        Schema::dropIfExists('admin_commission_payouts');
        Schema::dropIfExists('investor_payout_items');
        Schema::dropIfExists('interest_distribution_ledgers');
        Schema::dropIfExists('loan_admin_assignments');
        Schema::dropIfExists('loan_investor_distributions');
        Schema::dropIfExists('loan_distribution_configs');
        Schema::dropIfExists('admin_commission_profiles');
        Schema::dropIfExists('investor_interest_rates');
        Schema::dropIfExists('interest_configurations');
    }
};
