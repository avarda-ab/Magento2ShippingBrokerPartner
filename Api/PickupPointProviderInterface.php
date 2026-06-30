<?php

/**
 * @author Avarda Team
 * @copyright Copyright © Avarda. All rights reserved.
 */

declare(strict_types=1);

namespace Avarda\ShippingBrokerPartner\Api;

use Magento\Quote\Api\Data\AddressInterface;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * Pickup point adapter for one carrier, registered per carrier/method in ProviderPool.
 */
interface PickupPointProviderInterface
{
    public function supports(string $carrierCode, string $methodCode): bool;

    /**
     * Pickup points near the address. Entry shape: id, name, address1, zipCode,
     * city, carrierData. Returns [] to fall back to plain delivery.
     *
     * @return array[]
     */
    public function getPickupPoints(string $methodCode, AddressInterface $address): array;

    /**
     * Persist the selected pickup point onto the order from the shipping data.
     *
     * @param array $shippingData Decoded selected-option snapshot.
     */
    public function applyToOrder(OrderInterface $order, array $shippingData): void;
}
