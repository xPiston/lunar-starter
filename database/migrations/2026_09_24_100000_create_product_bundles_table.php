<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Product bundles: several variants sold together, at their own price.
 *
 * Lunar has no bundle concept, but it does not need one - a cart line points
 * at any `Lunar\Base\Purchasable`, and its own validators branch on whether
 * that purchasable is a variant. So a bundle is a purchasable of the
 * application's own, and Lunar's cart, pricing, tax, shipping and orders
 * treat it like anything else.
 *
 * The price lives in Lunar's polymorphic `lunar_prices` table, the same one
 * variants use, so currency and customer-group pricing work unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_bundles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            // A plain flag, not a `published_at` like the editorial content:
            // there is no scheduling to express here, and a flag on its own
            // cannot disagree with a date that does not exist.
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('product_bundle_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_bundle_id')->constrained()->cascadeOnDelete();
            // No foreign key to lunar_product_variants: Lunar owns that table.
            // A variant that disappears makes the bundle unfulfillable, which
            // the stock check below already reports - better than a cascade
            // silently shrinking a bundle to something a customer didn't buy.
            $table->unsignedBigInteger('product_variant_id');
            $table->unsignedInteger('quantity')->default(1);

            $table->unique(['product_bundle_id', 'product_variant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_bundle_items');
        Schema::dropIfExists('product_bundles');
    }
};
