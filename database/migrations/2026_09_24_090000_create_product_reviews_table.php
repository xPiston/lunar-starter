<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer reviews. The application's own table, like the editorial content:
 * Lunar has no review concept, and a product's rating is not a property of
 * the catalogue - it is what other people said about it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_reviews', function (Blueprint $table) {
            $table->id();
            // No foreign key to lunar_products: Lunar owns that table and its
            // deletes, and an orphaned review is harmless - it simply stops
            // being listed with a product that no longer exists.
            $table->unsignedBigInteger('product_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('body');
            // Decided once, when the review is written, from the order history
            // at that moment. Recomputing it later would let a refunded or
            // deleted order silently revoke a badge already shown.
            $table->boolean('verified_purchase')->default(false);
            // Null until a member of staff approves it. Same single source of
            // truth as content_pages.published_at: a separate boolean could
            // only ever disagree with the date.
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            // One review per customer per product: the second one is an edit,
            // not a second opinion, and without this a single account could
            // move a product's average on its own.
            $table->unique(['product_id', 'user_id']);
            $table->index(['product_id', 'approved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_reviews');
    }
};
