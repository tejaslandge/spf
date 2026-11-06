<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investor_payouts', function (Blueprint $table) {
            $table->id();
            $table->string('payout_code', 30)->unique();
            $table->foreignId('investor_id')->constrained()->restrictOnDelete();
            $table->date('period_month');
            $table->decimal('gross_interest', 15, 2)->default(0);
            $table->decimal('management_fee', 15, 2)->default(0);
            $table->decimal('tds_amount', 15, 2)->default(0);
            $table->decimal('net_amount', 15, 2)->default(0);
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['investor_id', 'period_month']);
            $table->index(['period_month', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investor_payouts');
    }
};
