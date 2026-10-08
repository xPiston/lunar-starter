<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When an order gave its units back.
 *
 * A row is deleted nowhere: cancelling and then un-cancelling an order is a
 * mistake staff make, and the two directions have to be as reliable as each
 * other. Null means the stock is still committed, a date means it has been
 * returned to the shelf and may be taken again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_stock_commitments', function (Blueprint $table) {
            $table->timestamp('released_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('order_stock_commitments', function (Blueprint $table) {
            $table->dropColumn('released_at');
        });
    }
};
