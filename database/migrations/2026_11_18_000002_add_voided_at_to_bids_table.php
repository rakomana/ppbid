<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A voided (withdrawn) bid is kept for the history but no longer counts.
     */
    public function up(): void
    {
        Schema::table('bids', function (Blueprint $table) {
            $table->timestamp('voided_at')->nullable()->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('bids', function (Blueprint $table) {
            $table->dropColumn('voided_at');
        });
    }
};
