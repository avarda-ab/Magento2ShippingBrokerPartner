<?php

/**
 * @author Avarda Team
 * @copyright Copyright © Avarda. All rights reserved.
 */

declare(strict_types=1);

namespace Avarda\ShippingBrokerPartner\Model\Rate;

use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Model\Quote\Address as QuoteAddress;
use Magento\Quote\Model\Quote\Address\Rate;

class Collector
{
    /** Broker placeholder carrier, filtered out to avoid recursing into ourselves. */
    protected const PLACEHOLDER_CARRIER = 'avarda';

    /**
     * All active shipping rates except the broker placeholder.
     *
     * @return Rate[]
     */
    public function collect(AddressInterface $address): array
    {
        if (!$address instanceof QuoteAddress) {
            return [];
        }
        $address->setCollectShippingRates(true);
        $address->collectShippingRates();

        $rates = [];
        foreach ($address->getAllShippingRates() as $rate) {
            if (!$rate instanceof Rate) {
                continue;
            }
            if ($rate->getCarrier() === self::PLACEHOLDER_CARRIER) {
                continue;
            }
            $rates[] = $rate;
        }
        return $rates;
    }
}
