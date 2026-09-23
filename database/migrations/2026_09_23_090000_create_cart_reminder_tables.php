<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bookkeeping for abandoned cart reminders.
 *
 * Kept in the application's own tables rather than as columns on
 * `lunar_carts`: Lunar owns that schema and may change it, and nothing here
 * is a property of a cart - it's a record of what this feature did about one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_reminders', function (Blueprint $table) {
            $table->id();
            // No foreign key to lunar_carts on purpose: the row has to outlive
            // the cart. If a cart is deleted and its id later reused, a
            // cascade would have dropped the record that stops a second email.
            $table->unsignedBigInteger('cart_id')->unique();
            $table->timestamp('sent_at');
        });

        Schema::create('cart_reminder_opt_outs', function (Blueprint $table) {
            $table->id();
            // Unsubscribing is per address, not per cart or per account: the
            // person asked to stop hearing from this, and a later guest
            // checkout with the same address is still them.
            $table->string('email')->unique();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_reminders');
        Schema::dropIfExists('cart_reminder_opt_outs');
    }
};
