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
 * Three guarantees the implementation owes:
 *
 * - **Once per order, each way.** Committing an order already committed does
 *   nothing, and so does releasing one already released. Nothing guarantees
 *   an order is only ever placed, or cancelled, once in a single process.
 * - **Both directions.** An order cancelled by mistake and put back is a
 *   thing that happens, so releasing is not final: a committed order may be
 *   released and committed again, and the shelf has to follow each time.
 * - **Nothing refuses.** By the time a commit runs the customer has paid.
 *   Stock that goes negative is a record of an oversell that already
 *   happened, and hiding it would not put the item back in the warehouse.
 */
interface StockLedger
{
    /**
     * Take what the order sold off the shelf.
     */
    public function commitOrder(int $orderId): void;

    /**
     * Put it back, for an order that is no longer going to happen.
     *
     * An order this ledger never committed gives nothing back. That is not an
     * edge case: orders placed before any of this existed, and orders holding
     * only products that never run out, have nothing to return.
     */
    public function releaseOrder(int $orderId): void;
}
