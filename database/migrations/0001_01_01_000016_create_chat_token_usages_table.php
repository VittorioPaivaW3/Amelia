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
        Schema::create('chat_token_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('gut_request_id')->nullable()->constrained('gut_requests')->nullOnDelete();
            $table->string('mode', 16)->nullable();
            $table->string('sector', 16)->nullable();
            $table->string('model', 64)->nullable();
            $table->string('conversation_id', 36)->nullable();
            $table->string('message_id', 36)->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('total_tokens')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['sector', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_token_usages');
    }
};
