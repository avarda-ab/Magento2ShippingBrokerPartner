<?php

/**
 * @author Avarda Team
 * @copyright Copyright © Avarda. All rights reserved.
 */

declare(strict_types=1);

namespace Avarda\ShippingBrokerPartner\Model\Auth;

use Avarda\ShippingBrokerPartner\Model\Config;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\Http as HttpRequest;

class BearerValidator
{
    protected Config $config;

    public function __construct(
        Config $config
    ) {
        $this->config = $config;
    }

    public function validate(RequestInterface $request): bool
    {
        $expected = $this->config->getPartnerSecret();
        if ($expected === '') {
            return false;
        }

        $header = $this->extractAuthorizationHeader($request);
        if ($header === null) {
            return false;
        }
        if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
            return false;
        }
        return hash_equals($expected, trim($m[1]));
    }

    private function extractAuthorizationHeader(RequestInterface $request): ?string
    {
        if ($request instanceof HttpRequest) {
            $value = $request->getHeader('Authorization');
            if ($value !== false && $value !== '') {
                return (string) $value;
            }
        }
        $server = (string) ($request->getServer('HTTP_AUTHORIZATION') ?? '');
        if ($server !== '') {
            return $server;
        }
        $redirect = (string) ($request->getServer('REDIRECT_HTTP_AUTHORIZATION') ?? '');
        return $redirect !== '' ? $redirect : null;
    }
}
