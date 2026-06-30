<?php

/**
 * @author Avarda Team
 * @copyright Copyright © Avarda. All rights reserved.
 */

declare(strict_types=1);

namespace Avarda\ShippingBrokerPartner\Model\PickupPoint;

use Avarda\ShippingBrokerPartner\Api\PickupPointProviderInterface;
use InvalidArgumentException;

/**
 * Pickup point providers, registered via di.xml. First match wins.
 */
class ProviderPool
{
    protected array $providers;

    /**
     * @param PickupPointProviderInterface[] $providers
     */
    public function __construct(
        array $providers = []
    ) {
        $this->providers = $providers;
        foreach ($this->providers as $name => $provider) {
            if (!$provider instanceof PickupPointProviderInterface) {
                throw new InvalidArgumentException(sprintf(
                    'Pickup point provider "%s" must implement %s',
                    (string) $name,
                    PickupPointProviderInterface::class
                ));
            }
        }
    }

    public function getForMethod(string $carrierCode, string $methodCode): ?PickupPointProviderInterface
    {
        foreach ($this->providers as $provider) {
            if ($provider->supports($carrierCode, $methodCode)) {
                return $provider;
            }
        }
        return null;
    }
}
