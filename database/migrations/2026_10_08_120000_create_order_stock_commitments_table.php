<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per order whose stock has been taken off the shelf.
 *
 * The primary key is the order, which is the whole point: taking stock twice
 * for one order is the failure mode this table exists to make impossible. An
 * insert that collides is an order already accounted for, not an error.
 *
 * It also answers "was this order ever destocked?" without re-deriving it from
 * the lines, which is what a restock on cancellation will need.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_stock_commitments', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id')->primary();
            $table->timestamp('committed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_stock_commitments');
    }
};
