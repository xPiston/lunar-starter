<?php

declare(strict_types=1);

namespace App\Application\Cart;

use App\Domain\Cart\Port\CartReminders;

final readonly class OptOutOfCartReminders
{
    public function __construct(private CartReminders $reminders) {}

    public function handle(string $email): void
    {
        $this->reminders->optOut($email);
    }
}
