<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Custom pages and articles. Like hero_slides, this is the application's own
 * editorial content rather than anything Lunar knows about.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_pages', function (Blueprint $table) {
            $table->id();
            // 'page' or 'post' - see App\Domain\Content\ContentType. Kept as a
            // plain string rather than a database enum so adding a type stays
            // a code change, not a migration.
            $table->string('type')->default('page');
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('excerpt')->nullable();
            $table->text('body');
            $table->string('image_path')->nullable();
            // Single source of truth for "is this live": null is a draft, a
            // future date is scheduled, anything past is published. A
            // separate boolean would let the two disagree.
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_pages');
    }
};
