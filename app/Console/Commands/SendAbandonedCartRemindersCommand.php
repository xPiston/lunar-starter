<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Cart\SendAbandonedCartReminders;
use Illuminate\Console\Command;

/**
 * Scheduled hourly in routes/console.php. Nothing is sent unless the
 * scheduler runs, which in this template's production topology is the
 * dedicated `scheduler` service (see docker-compose.prod.yml).
 */
final class SendAbandonedCartRemindersCommand extends Command
{
    protected $signature = 'carts:send-abandoned-reminders';

    protected $description = 'Email a recovery link to anyone who left a cart behind';

    public function handle(SendAbandonedCartReminders $sendReminders): int
    {
        if (! config('abandoned_carts.enabled')) {
            $this->components->warn('Abandoned cart reminders are disabled (ABANDONED_CART_REMINDERS_ENABLED).');

            return self::SUCCESS;
        }

        $carts = $sendReminders->handle();

        $this->components->info(match (count($carts)) {
            0 => 'No abandoned carts to remind about.',
            1 => 'Queued 1 reminder.',
            default => 'Queued '.count($carts).' reminders.',
        });

        return self::SUCCESS;
    }
}
