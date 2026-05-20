<?php

/**
 * @author Avarda Team
 * @copyright Copyright © Avarda. All rights reserved.
 */

declare(strict_types=1);

namespace Avarda\ShippingBrokerPartner\Model\Provider;

use Avarda\ShippingBroker\Api\Gateway\Response\ParserInterface;
use Avarda\ShippingBroker\Api\ProviderInterface;

class Partner implements ProviderInterface
{
    public const string CODE = 'partner';

    public function __construct(
        private readonly ParserInterface $responseParser
    ) {
    }

    public function getCode(): string
    {
        return self::CODE;
    }

    public function getResponseParser(): ParserInterface
    {
        return $this->responseParser;
    }

    public function getCustomAttributesPool(): array
    {
        return [];
    }

    public function shouldInjectFallbackLine(): bool
    {
        return false;
    }

    public function shouldLoadUnifaunScript(): bool
    {
        return false;
    }
}
