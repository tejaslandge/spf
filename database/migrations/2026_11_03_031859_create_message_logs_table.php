<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_logs', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 20)->index();
            $table->string('recipient', 30)->index();
            $table->string('template', 100)->nullable();
            $table->jsonb('payload')->nullable();
            $table->string('status', 20)->default('queued')->index();
            $table->string('provider', 30)->nullable();
            $table->string('provider_message_id')->nullable();
            $table->text('error')->nullable();
            $table->nullableMorphs('related');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_logs');
    }
};
