<?php

declare(strict_types=1);

namespace App\Domain\Checkout;

final readonly class PaymentIntent
{
    public function __construct(
        public string $clientSecret,
        public string $publishableKey,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'client_secret' => $this->clientSecret,
            'publishable_key' => $this->publishableKey,
        ];
    }
}
