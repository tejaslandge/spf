<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('borrowers', function (Blueprint $table) {
            $table->id();
            $table->string('borrower_code', 30)->unique();
            $table->string('full_name');
            $table->text('address');
            $table->string('mobile', 20)->unique();
            $table->string('alternate_mobile', 20)->nullable();
            $table->string('emergency_contact_name');
            $table->string('emergency_contact_number', 20);
            $table->string('status', 20)->default('active')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('full_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('borrowers');
    }
};
