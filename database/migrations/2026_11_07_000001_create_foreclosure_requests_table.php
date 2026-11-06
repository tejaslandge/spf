<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('foreclosure_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('borrower_id')->constrained('borrowers')->cascadeOnDelete();
            $table->decimal('outstanding_principal', 15, 2)->default(0);
            $table->decimal('accrued_interest', 15, 2)->default(0);
            $table->decimal('foreclosure_charges', 15, 2)->default(0);
            $table->decimal('total_settlement', 15, 2)->default(0);
            $table->decimal('other_outstanding_dues', 15, 2)->default(0);
            $table->decimal('eligible_adjustments', 15, 2)->default(0);
            $table->date('foreclosure_request_date');
            $table->date('effective_foreclosure_date');
            $table->enum('approval_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->enum('payment_status', ['unpaid', 'paid', 'refunded'])->default('unpaid');
            $table->date('payment_date')->nullable();
            $table->date('actual_closure_date')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['loan_id', 'approval_status']);
            $table->index(['borrower_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('foreclosure_requests');
    }
};