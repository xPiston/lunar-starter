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
        /**
         * The ISO 3166-1 alpha-2 code, next to `countryId`.
         *
         * `countryId` is a row id in Lunar's country table: it identifies a
         * country only to this installation, and means nothing to anyone
         * else. Anything outside the shop - an ERP, a carrier, a tax engine -
         * works in codes, so the one place that can map between the two, the
         * adapter that already read the row, resolves it once.
         */
        public ?string $countryIso = null,
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
            countryIso: $data['country_iso'] ?? null,
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
            'country_iso' => $this->countryIso,
        ];
    }
}
