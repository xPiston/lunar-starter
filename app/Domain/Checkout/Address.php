<?php

declare(strict_types=1);

namespace App\Domain\Checkout;

final readonly class Address
{
    public function __construct(
        public ?int $countryId,
        public string $firstName,
        public string $lastName,
        public ?string $companyName,
        public string $lineOne,
        public ?string $lineTwo,
        public string $city,
        public ?string $state,
        public string $postcode,
        public ?string $contactEmail,
        public ?string $contactPhone,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            countryId: isset($data['country_id']) ? (int) $data['country_id'] : null,
            firstName: (string) ($data['first_name'] ?? ''),
            lastName: (string) ($data['last_name'] ?? ''),
            companyName: $data['company_name'] ?? null,
            lineOne: (string) ($data['line_one'] ?? ''),
            lineTwo: $data['line_two'] ?? null,
            city: (string) ($data['city'] ?? ''),
            state: $data['state'] ?? null,
            postcode: (string) ($data['postcode'] ?? ''),
            contactEmail: $data['contact_email'] ?? null,
            contactPhone: $data['contact_phone'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'country_id' => $this->countryId,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'company_name' => $this->companyName,
            'line_one' => $this->lineOne,
            'line_two' => $this->lineTwo,
            'city' => $this->city,
            'state' => $this->state,
            'postcode' => $this->postcode,
            'contact_email' => $this->contactEmail,
            'contact_phone' => $this->contactPhone,
        ];
    }
}
