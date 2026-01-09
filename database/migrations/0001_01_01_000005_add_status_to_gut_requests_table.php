<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gut_requests', function (Blueprint $table) {
            $table->string('status', 30)->default('novo')->after('score');
        });
    }

    public function down(): void
    {
        Schema::table('gut_requests', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
