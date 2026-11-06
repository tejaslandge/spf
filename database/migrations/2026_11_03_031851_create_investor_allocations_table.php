<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investor_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investor_id')->constrained()->restrictOnDelete();
            $table->foreignId('loan_id')->constrained()->restrictOnDelete();
            $table->decimal('principal_amount', 15, 2);
            $table->date('allocation_date');
            $table->string('status', 20)->default('active')->index();
            $table->timestamp('released_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['investor_id', 'loan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investor_allocations');
    }
};
