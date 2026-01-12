<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gut_requests', function (Blueprint $table) {
            $table->foreignId('accepted_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->after('rejection_reason');
        });
    }

    public function down(): void
    {
        Schema::table('gut_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('accepted_by');
        });
    }
};
