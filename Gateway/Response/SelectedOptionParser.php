<?php

/**
 * @author Avarda Team
 * @copyright Copyright © Avarda. All rights reserved.
 */

declare(strict_types=1);

namespace Avarda\ShippingBrokerPartner\Gateway\Response;

use Avarda\ShippingBroker\Api\Gateway\Response\ParserInterface;

/**
 * Parses Partner Shipping `selectedShippingOption` from Avarda's getPaymentStatus
 * response. Avarda mirrors what the implementor (i.e. this Magento store)
 * returned, so the shape is:
 *   modules[*].selectedShippingOption: {
 *       shippingMethod, deliveryType, carrier, product, price, currency
 *   }
 *
 * The base carrier/handler expect at least `selectedOptionName`, `price`, and
 * `widgetAgent` keys, so we map into that shape while keeping the original
 * partner-shape fields available for downstream consumers.
 */
class SelectedOptionParser implements ParserInterface
{
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
            $selected = $this->getCaseInsensitive($module, 'SelectedShippingOption');
            if (!is_array($selected) || $selected === []) {
                continue;
            }

            $shippingMethod = (string) ($this->getCaseInsensitive($selected, 'ShippingMethod') ?? '');
            $deliveryType   = (string) ($this->getCaseInsensitive($selected, 'DeliveryType') ?? '');
            $carrier        = (string) ($this->getCaseInsensitive($selected, 'Carrier') ?? '');
            $product        = (string) ($this->getCaseInsensitive($selected, 'Product') ?? '');
            $price          = (float)  ($this->getCaseInsensitive($selected, 'Price') ?? 0.0);
            $currency       = (string) ($this->getCaseInsensitive($selected, 'Currency') ?? '');

            $name = trim($carrier . ' ' . $product);
            if ($name === '') {
                $name = $product !== '' ? $product : $carrier;
            }

            return [
                'selectedOptionName' => $name,
                'price'              => $price,
                'shippingMethod'     => $shippingMethod,
                'deliveryType'       => $deliveryType,
                'carrier'            => $carrier,
                'product'            => $product,
                'currency'           => $currency,
                // Compatibility shim for existing nShift-shaped consumers
                'selectedAgentId'    => null,
                'optionId'           => $shippingMethod,
                'carrierId'          => $carrier,
                'priceValue'         => $price,
                'defaultPrice'       => $price,
                'taxRate'            => null,
                'serviceId'          => $shippingMethod,
                'widgetAgent'        => null,
            ];
        }

        return false;
    }

    /**
     * Look up a key tolerating PascalCase / camelCase / lower differences.
     */
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
