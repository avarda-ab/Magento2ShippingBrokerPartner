<?php

/**
 * @author Avarda Team
 * @copyright Copyright © Avarda. All rights reserved.
 */

declare(strict_types=1);

namespace Avarda\ShippingBrokerPartner\Model\Quote;

use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\CartInterface;

/**
 * Mirrors the deliveryAddress field from a partner-session payload onto the
 * quote's shipping address. We never persist the quote here — just mutate the
 * in-memory object so collectShippingRates() runs against the latest input.
 */
class AddressApplier
{
    public function apply(CartInterface $quote, array $deliveryAddress): AddressInterface
    {
        $address = $quote->getShippingAddress();

        if (!empty($deliveryAddress['country'])) {
            $address->setCountryId((string) $deliveryAddress['country']);
        }
        if (!empty($deliveryAddress['zip'])) {
            $address->setPostcode((string) $deliveryAddress['zip']);
        }
        if (!empty($deliveryAddress['city'])) {
            $address->setCity((string) $deliveryAddress['city']);
        }
        if (isset($deliveryAddress['address1']) || isset($deliveryAddress['address2'])) {
            $street = array_values(array_filter([
                (string) ($deliveryAddress['address1'] ?? ''),
                (string) ($deliveryAddress['address2'] ?? ''),
            ], static fn ($line) => $line !== ''));
            $address->setStreet($street);
        }
        if (!empty($deliveryAddress['firstName'])) {
            $address->setFirstname((string) $deliveryAddress['firstName']);
        }
        if (!empty($deliveryAddress['lastName'])) {
            $address->setLastname((string) $deliveryAddress['lastName']);
        }

        return $address;
    }
}
