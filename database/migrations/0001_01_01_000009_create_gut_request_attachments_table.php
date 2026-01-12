<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gut_request_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gut_request_id')->nullable()->constrained('gut_requests')->nullOnDelete();
            $table->string('conversation_id', 36)->index();
            $table->string('message_id', 36)->index();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 120);
            $table->unsignedBigInteger('size');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gut_request_attachments');
    }
};
