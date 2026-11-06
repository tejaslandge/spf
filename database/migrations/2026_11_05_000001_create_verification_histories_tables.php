<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kyc_verification_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kyc_document_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->string('action', 50)->index();
            $table->string('old_status', 20)->nullable();
            $table->string('new_status', 20)->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('acted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id', 'created_at']);
            $table->index(['kyc_document_id', 'created_at']);
        });

        Schema::create('deposit_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investor_deposit_id')->constrained()->cascadeOnDelete();
            $table->string('old_status', 20)->nullable();
            $table->string('new_status', 20);
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['investor_deposit_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deposit_status_histories');
        Schema::dropIfExists('kyc_verification_histories');
    }
};
