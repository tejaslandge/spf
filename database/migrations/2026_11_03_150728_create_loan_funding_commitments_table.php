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
        Schema::create('loan_funding_commitments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('investor_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('status', 20)->default('confirmed')->index();
            $table->timestamp('confirmed_at')->useCurrent();
            $table->timestamp('withdrawn_at')->nullable();
            $table->string('transaction_reference')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['loan_id', 'investor_id']);
            $table->index(['loan_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_funding_commitments');
    }
};
