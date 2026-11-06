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
        Schema::create('loan_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->decimal('principal_amount', 15, 2);
            $table->decimal('annual_interest_rate', 7, 4);
            $table->string('interest_method', 20);
            $table->unsignedSmallInteger('tenure_months');
            $table->decimal('emi_amount', 15, 2);
            $table->decimal('total_interest', 15, 2);
            $table->decimal('total_repayment', 15, 2);
            $table->decimal('processing_fee', 15, 2)->default(0);
            $table->decimal('other_charges', 15, 2)->default(0);
            $table->jsonb('comparison')->nullable();
            $table->timestamp('offered_at')->useCurrent();
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['loan_id', 'accepted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_offers');
    }
};
