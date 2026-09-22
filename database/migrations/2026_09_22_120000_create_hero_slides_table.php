<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Editorial content for the homepage slider. Not a Lunar concept - Lunar owns
 * the catalog, not the storefront's marketing surface - so this is a plain
 * table of the application's own, reachable through App\Domain\Content.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hero_slides', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('subtitle')->nullable();
            // Path on the `public` disk, not a URL: where the file is served
            // from depends on APP_URL and the disk configuration, which are
            // deployment concerns and must not be frozen into a row.
            $table->string('image_path');
            $table->string('link_url')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            // The storefront's only query: visible slides in display order.
            $table->index(['is_visible', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hero_slides');
    }
};
