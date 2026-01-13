<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gut_requests', function (Blueprint $table) {
            $table->text('original_message')->nullable()->after('message');
            $table->text('original_response_text')->nullable()->after('response_text');
        });
    }

    public function down(): void
    {
        Schema::table('gut_requests', function (Blueprint $table) {
            $table->dropColumn(['original_message', 'original_response_text']);
        });
    }
};
