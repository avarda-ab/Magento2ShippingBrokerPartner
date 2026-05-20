<?php

/**
 * @author Avarda Team
 * @copyright Copyright © Avarda. All rights reserved.
 */

declare(strict_types=1);

namespace Avarda\ShippingBrokerPartner\Model\Rate;

use Avarda\ShippingBrokerPartner\Model\Config;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote\Address as QuoteAddress;
use Magento\Quote\Model\Quote\Address\Rate;

class Collector
{
    /**
     * Carrier code of the placeholder rate the broker module exposes — must be
     * filtered out, otherwise we'd recurse into our own carrier when collecting
     * rates from the partner endpoint.
     */
    private const string PLACEHOLDER_CARRIER = 'avarda';

    public function __construct(
        private readonly Config $config
    ) {
    }

    /**
     * @return Rate[]
     */
    public function collect(CartInterface $quote, AddressInterface $address): array
    {
        if (!$address instanceof QuoteAddress) {
            return [];
        }
        $address->setCollectShippingRates(true);
        $address->collectShippingRates();

        $allowed = $this->config->getAllowedMethods((int) $quote->getStoreId());
        $rates = [];
        foreach ($address->getAllShippingRates() as $rate) {
            if (!$rate instanceof Rate) {
                continue;
            }
            if ($rate->getCarrier() === self::PLACEHOLDER_CARRIER) {
                continue;
            }
            if ($allowed !== [] && !in_array($rate->getCode(), $allowed, true)) {
                continue;
            }
            $rates[] = $rate;
        }
        return $rates;
    }
}
