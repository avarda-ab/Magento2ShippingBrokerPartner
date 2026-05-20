<?php

/**
 * @author Avarda Team
 * @copyright Copyright © Avarda. All rights reserved.
 */

declare(strict_types=1);

namespace Avarda\ShippingBrokerPartner\Model\Modules;

use Magento\Framework\Serialize\Serializer\Json;

/**
 * Builds the opaque `modules` payload Avarda hands back to our widget via
 * avardaShipping.init({ config: { modules } }). Schema is owned by this
 * module since the same module also implements the widget renderer.
 */
class PayloadBuilder
{
    public function __construct(
        private readonly Json $serializer
    ) {
    }

    /**
     * @param array[] $options Option entries as produced by OptionMapper::toOption
     * @param string|null $selectedShippingMethod Selected option id, when known
     */
    public function build(array $options, ?string $selectedShippingMethod = null): string
    {
        $entries = [];
        foreach ($options as $option) {
            $id = (string) ($option['shippingMethod'] ?? '');
            if ($id === '') {
                continue;
            }
            $entries[] = [
                'id'       => $id,
                'title'    => trim((string) ($option['carrier'] ?? '') . ' ' . (string) ($option['product'] ?? '')) ?: $id,
                'carrier'  => (string) ($option['carrier'] ?? ''),
                'product'  => (string) ($option['product'] ?? ''),
                'price'    => (float) ($option['price'] ?? 0.0),
                'currency' => (string) ($option['currency'] ?? ''),
                'selected' => $selectedShippingMethod !== null && $selectedShippingMethod === $id,
            ];
        }
        return $this->serializer->serialize(['options' => $entries]);
    }
}
