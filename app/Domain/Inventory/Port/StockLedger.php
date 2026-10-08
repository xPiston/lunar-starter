<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Port;

/**
 * Takes the stock an order consumed off the shelf.
 *
 * Deliberately thin. What a unit of stock *is* - a variant row, a bundle made
 * of several of them, a policy that says this product never runs out - is
 * catalogue vocabulary the domain has no use for, and expressing it here would
 * drag Lunar's model of inventory into a layer that is meant to survive its
 * replacement. The order is identified by its id for the same reason.
 *
 * Two guarantees the implementation owes:
 *
 * - **Once per order.** Called twice for the same order, the second call does
 *   nothing. Nothing guarantees an order is only ever placed once in a single
 *   process.
 * - **Nothing refuses.** By the time this runs the customer has paid. Stock
 *   that goes negative is a record of an oversell that already happened, and
 *   hiding it would not put the item back in the warehouse.
 */
interface StockLedger
{
    public function commitOrder(int $orderId): void;
}
