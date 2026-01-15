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
        Schema::table('chat_token_usages', function (Blueprint $table) {
            $table->decimal('input_cost', 12, 6)->default(0)->after('input_tokens');
            $table->decimal('output_cost', 12, 6)->default(0)->after('output_tokens');
            $table->decimal('total_cost', 12, 6)->default(0)->after('total_tokens');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_token_usages', function (Blueprint $table) {
            $table->dropColumn(['input_cost', 'output_cost', 'total_cost']);
        });
    }
};
