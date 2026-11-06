<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investors', function (Blueprint $table) {
            $table->id();
            $table->string('investor_code', 30)->unique();
            $table->string('name');
            $table->text('address');
            $table->string('mobile', 20)->unique();
            $table->string('email')->nullable();
            $table->string('pan_number', 20)->nullable();
            $table->string('aadhaar_number', 20)->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number', 40)->nullable();
            $table->string('bank_ifsc', 20)->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investors');
    }
};
