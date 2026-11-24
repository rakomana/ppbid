<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An optional Buy Now price per listing, and a flag on the bid that bought it.
     */
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->decimal('buy_now_price', 10, 2)->nullable()->after('min_price');
        });

        Schema::table('bids', function (Blueprint $table) {
            $table->boolean('is_buy_now')->default(false)->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('bids', function (Blueprint $table) {
            $table->dropColumn('is_buy_now');
        });

        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn('buy_now_price');
        });
    }
};
