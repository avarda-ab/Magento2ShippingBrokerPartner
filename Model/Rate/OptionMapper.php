<?php

/**
 * @author Avarda Team
 * @copyright Copyright © Avarda. All rights reserved.
 */

declare(strict_types=1);

namespace Avarda\ShippingBrokerPartner\Model\Rate;

use Magento\Quote\Model\Quote\Address\Rate;

/**
 * Maps a Magento shipping rate into the partner-shipping selectedShippingOption
 * shape returned by our endpoints.
 */
class OptionMapper
{
    public function toOption(Rate $rate, string $currency): array
    {
        $code = $rate->getCode() !== null
            ? (string) $rate->getCode()
            : trim((string) $rate->getCarrier() . '_' . (string) $rate->getMethod(), '_');

        return [
            'shippingMethod' => $code,
            'deliveryType'   => 'delivery',
            'carrier'        => $rate->getCarrierTitle() ?: (string) $rate->getCarrier(),
            'product'        => $rate->getMethodTitle() ?: (string) $rate->getMethod(),
            'price'          => (float) $rate->getPrice(),
            'currency'       => $currency,
        ];
    }

    /**
     * @param Rate[] $rates
     * @return array[]
     */
    public function toOptions(array $rates, string $currency): array
    {
        $options = [];
        foreach ($rates as $rate) {
            $options[] = $this->toOption($rate, $currency);
        }
        return $options;
    }
}
