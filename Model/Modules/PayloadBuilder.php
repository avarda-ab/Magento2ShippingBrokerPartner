<?php

/**
 * @author Avarda Team
 * @copyright Copyright © Avarda. All rights reserved.
 */

declare(strict_types=1);

namespace Avarda\ShippingBrokerPartner\Model\Modules;

use Magento\Framework\Serialize\Serializer\Json;

/**
 * Builds the `modules` payload Avarda passes back to our widget. Schema is ours.
 */
class PayloadBuilder
{
    protected Json $serializer;

    public function __construct(
        Json $serializer
    ) {
        $this->serializer = $serializer;
    }

    /**
     * @param array[] $options Option entries as produced by OptionMapper::toOption,
     *                         optionally enriched with deliveryType/pickupPoints
     * @param string|null $selectedShippingMethod Selected option id, when known
     * @param string|null $selectedPickupPointId Selected pickup point id, when known
     */
    public function build(
        array $options,
        ?string $selectedShippingMethod = null,
        ?string $selectedPickupPointId = null
    ): string {
        $entries = [];
        foreach ($options as $option) {
            $id = (string) ($option['shippingMethod'] ?? '');
            if ($id === '') {
                continue;
            }
            $selected = $selectedShippingMethod !== null && $selectedShippingMethod === $id;
            $entry = [
                'id'           => $id,
                'title'        => trim((string) ($option['carrier'] ?? '') . ' ' . (string) ($option['product'] ?? '')) ?: $id,
                'carrier'      => (string) ($option['carrier'] ?? ''),
                'product'      => (string) ($option['product'] ?? ''),
                'price'        => (float) ($option['price'] ?? 0.0),
                'currency'     => (string) ($option['currency'] ?? ''),
                'deliveryType' => (string) ($option['deliveryType'] ?? 'delivery'),
                'carrierCode'  => (string) ($option['carrierCode'] ?? ''),
                'methodCode'   => (string) ($option['methodCode'] ?? ''),
                'selected'     => $selected,
            ];
            if (!empty($option['pickupPoints']) && is_array($option['pickupPoints'])) {
                $entry['pickupPoints'] = array_values($option['pickupPoints']);
                if ($selected && $selectedPickupPointId !== null) {
                    $entry['selectedPickupPointId'] = $selectedPickupPointId;
                }
            }
            $entries[] = $entry;
        }
        return $this->serializer->serialize(['options' => $entries]);
    }
}
