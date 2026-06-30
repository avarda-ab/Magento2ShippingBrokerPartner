<?php

/**
 * @author Avarda Team
 * @copyright Copyright © Avarda. All rights reserved.
 */

declare(strict_types=1);

namespace Avarda\ShippingBrokerPartner\Gateway\Response;

use Avarda\ShippingBroker\Api\Gateway\Response\ParserInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Throwable;

/**
 * Reads the selected option (and pickup point) from Avarda's getPaymentStatus
 * response and maps it to the shape the base broker carrier/handler expect
 * (selectedOptionName, price, widgetAgent).
 */
class SelectedOptionParser implements ParserInterface
{
    protected Json $serializer;

    public function __construct(
        Json $serializer
    ) {
        $this->serializer = $serializer;
    }

    public function parse(array $response): array|bool
    {
        $modules = $this->getCaseInsensitive($response, 'Modules');
        if (!is_array($modules) || $modules === []) {
            return false;
        }

        foreach ($modules as $module) {
            if (!is_array($module)) {
                continue;
            }
            // Avarda nests the session a level deeper; fall back to the module.
            $session = $this->getCaseInsensitive($module, 'ExternalShippingSession');
            $container = is_array($session) ? $session : $module;

            $selected = $this->getCaseInsensitive($container, 'SelectedShippingOption');
            if (!is_array($selected) || $selected === []) {
                continue;
            }

            $shippingMethod = (string) ($this->getCaseInsensitive($selected, 'ShippingMethod') ?? '');
            $deliveryType   = (string) ($this->getCaseInsensitive($selected, 'DeliveryType') ?? '');
            $carrier        = (string) ($this->getCaseInsensitive($selected, 'Carrier') ?? '');
            $product        = (string) ($this->getCaseInsensitive($selected, 'Product') ?? '');
            $price          = (float)  ($this->getCaseInsensitive($selected, 'Price') ?? 0.0);
            $currency       = (string) ($this->getCaseInsensitive($selected, 'Currency') ?? '');

            $pickupPoint = $this->extractPickupPoint($container, $shippingMethod);

            $name = trim($carrier . ' ' . $product);
            if ($name === '') {
                $name = $product !== '' ? $product : $carrier;
            }
            if ($pickupPoint !== null && ($pickupPoint['name'] ?? '') !== '') {
                $name .= ' (' . $pickupPoint['name'] . ')';
            }

            return [
                'selectedOptionName' => $name,
                'price'              => $price,
                'shippingMethod'     => $shippingMethod,
                'deliveryType'       => $deliveryType,
                'carrier'            => $carrier,
                'product'            => $product,
                'currency'           => $currency,
                'pickupPoint'        => $pickupPoint,
                // Compatibility shim for existing nShift-shaped consumers
                'selectedAgentId'    => $pickupPoint['id'] ?? null,
                'optionId'           => $shippingMethod,
                'carrierId'          => $carrier,
                'priceValue'         => $price,
                'defaultPrice'       => $price,
                'taxRate'            => null,
                'serviceId'          => $shippingMethod,
                'widgetAgent'        => $pickupPoint !== null ? [
                    'name'     => (string) ($pickupPoint['name'] ?? ''),
                    'address1' => (string) ($pickupPoint['address1'] ?? ''),
                    'zipCode'  => (string) ($pickupPoint['zipCode'] ?? ''),
                    'city'     => (string) ($pickupPoint['city'] ?? ''),
                ] : null,
            ];
        }

        return false;
    }

    private function extractPickupPoint(array $container, string $shippingMethod): ?array
    {
        $raw = $this->getCaseInsensitive($container, 'Modules');
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        try {
            $decoded = $this->serializer->unserialize($raw);
        } catch (Throwable $e) {
            return null;
        }
        if (!is_array($decoded) || !is_array($decoded['options'] ?? null)) {
            return null;
        }

        foreach ($decoded['options'] as $option) {
            if (!is_array($option) || ($option['id'] ?? '') !== $shippingMethod) {
                continue;
            }
            $pointId = (string) ($option['selectedPickupPointId'] ?? '');
            $points = is_array($option['pickupPoints'] ?? null) ? $option['pickupPoints'] : [];
            if ($points === []) {
                return null;
            }
            foreach ($points as $point) {
                if (is_array($point) && (string) ($point['id'] ?? '') === $pointId) {
                    return $point;
                }
            }
            // Fall back to the first point, like the backend does.
            $first = $points[array_key_first($points)];
            return is_array($first) ? $first : null;
        }

        return null;
    }

    private function getCaseInsensitive(array $haystack, string $key): mixed
    {
        if (array_key_exists($key, $haystack)) {
            return $haystack[$key];
        }
        $needle = strtolower($key);
        foreach ($haystack as $k => $v) {
            if (is_string($k) && strtolower($k) === $needle) {
                return $v;
            }
        }
        return null;
    }
}
