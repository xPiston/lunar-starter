<?php

declare(strict_types=1);

namespace App\Infrastructure\Lunar\Checkout;

use App\Domain\Checkout\Address;
use App\Domain\Checkout\Country;
use Lunar\Models\CartAddress as LunarCartAddress;
use Lunar\Models\Country as LunarCountry;
use Lunar\Models\OrderAddress as LunarOrderAddress;

final class AddressMapper
{
    /**
     * `CartAddress` (cart) and `OrderAddress` (placed order) share exactly
     * the same fields (see their migrations) but don't share a common base
     * class that's useful here - a union type is simpler than a dedicated
     * interface for this single read-only need.
     */
    public function toDomain(LunarCartAddress|LunarOrderAddress $address): Address
    {
        return new Address(
            countryId: $address->country_id,
            firstName: (string) $address->first_name,
            lastName: (string) $address->last_name,
            companyName: $address->company_name,
            lineOne: (string) $address->line_one,
            lineTwo: $address->line_two,
            city: (string) $address->city,
            state: $address->state,
            postcode: (string) $address->postcode,
            contactEmail: $address->contact_email,
            contactPhone: $address->contact_phone,
        );
    }

    public function countryToDomain(LunarCountry $country): Country
    {
        return new Country(
            id: $country->id,
            name: $country->name,
            iso2: $country->iso2,
        );
    }
}
