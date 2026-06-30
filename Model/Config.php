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
    public const XML_PATH_PARTNER_SECRET = 'carriers/avarda/partner_secret';
    public const XML_PATH_PARTNER_SESSION_TTL = 'carriers/avarda/partner_session_ttl';

    public const DEFAULT_SESSION_TTL = 3600;

    protected ScopeConfigInterface $scopeConfig;
    protected EncryptorInterface $encryptor;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        EncryptorInterface $encryptor
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->encryptor = $encryptor;
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
}
