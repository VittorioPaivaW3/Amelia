<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gut_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('message');
            $table->string('sector', 20);
            $table->unsignedTinyInteger('gravity');
            $table->unsignedTinyInteger('urgency');
            $table->unsignedTinyInteger('trend');
            $table->unsignedInteger('score');
            $table->text('response_text');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gut_requests');
    }
};
