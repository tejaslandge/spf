<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collaterals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->string('item');
            $table->text('description')->nullable();
            $table->decimal('estimated_value', 15, 2)->default(0);
            $table->decimal('quantity', 10, 2)->default(1);
            $table->string('identification_details')->nullable();
            $table->text('remarks')->nullable();
            $table->string('verification_status', 20)->default('unverified')->index();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['loan_id', 'verification_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collaterals');
    }
};
