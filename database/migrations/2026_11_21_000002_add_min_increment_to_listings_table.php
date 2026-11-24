<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An optional per-listing bid increment; empty uses AUCTION_MIN_INCREMENT.
     */
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->decimal('min_increment', 10, 2)->nullable()->after('buy_now_price');
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn('min_increment');
        });
    }
};
