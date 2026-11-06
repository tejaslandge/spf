<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->string('loan_code', 30)->unique();
            $table->foreignId('borrower_id')->constrained()->restrictOnDelete();
            $table->decimal('principal_amount', 15, 2);
            $table->decimal('annual_interest_rate', 7, 4);
            $table->string('interest_method', 20);
            $table->unsignedSmallInteger('tenure_months');
            $table->string('emi_frequency', 20)->default('monthly');
            $table->date('disbursement_date')->nullable();
            $table->date('first_emi_date');
            $table->decimal('emi_amount', 15, 2)->default(0);
            $table->decimal('total_interest', 15, 2)->default(0);
            $table->decimal('total_payable', 15, 2)->default(0);
            $table->decimal('outstanding_principal', 15, 2)->default(0);
            $table->decimal('outstanding_interest', 15, 2)->default(0);
            $table->decimal('foreclosure_charge', 15, 2)->default(0);
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['borrower_id', 'status']);
            $table->index(['status', 'first_emi_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
