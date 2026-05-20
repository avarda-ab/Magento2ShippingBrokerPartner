<?php

/**
 * @author Avarda Team
 * @copyright Copyright © Avarda. All rights reserved.
 */

declare(strict_types=1);

namespace Avarda\ShippingBrokerPartner\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    public const string XML_PATH_PARTNER_SECRET = 'carriers/avarda/partner_secret';
    public const string XML_PATH_PARTNER_SESSION_TTL = 'carriers/avarda/partner_session_ttl';
    public const string XML_PATH_PARTNER_ALLOWED_METHODS = 'carriers/avarda/partner_allowed_methods';

    public const int DEFAULT_SESSION_TTL = 3600;

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor
    ) {
    }

    public function getPartnerSecret(?int $storeId = null): string
    {
        $encrypted = (string) $this->scopeConfig->getValue(
            self::XML_PATH_PARTNER_SECRET,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        if ($encrypted === '') {
            return '';
        }
        return (string) $this->encryptor->decrypt($encrypted);
    }

    public function getSessionTtlSeconds(?int $storeId = null): int
    {
        $value = (int) $this->scopeConfig->getValue(
            self::XML_PATH_PARTNER_SESSION_TTL,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        return $value > 0 ? $value : self::DEFAULT_SESSION_TTL;
    }

    /**
     * @return string[] Empty array means "all active methods are allowed".
     */
    public function getAllowedMethods(?int $storeId = null): array
    {
        $raw = (string) $this->scopeConfig->getValue(
            self::XML_PATH_PARTNER_ALLOWED_METHODS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        if ($raw === '') {
            return [];
        }
        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }
}
