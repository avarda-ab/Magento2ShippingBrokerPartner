<?php

/**
 * @author Avarda Team
 * @copyright Copyright © Avarda. All rights reserved.
 */

declare(strict_types=1);

namespace Avarda\ShippingBrokerPartner\ViewModel;

use Avarda\ShippingBroker\Model\Provider\Pool;
use Avarda\ShippingBrokerPartner\Controller\Router as PartnerRouter;
use Avarda\ShippingBrokerPartner\Model\Provider\Partner;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Psr\Log\LoggerInterface;

/**
 * Backs the widget template: whether to render and the widget endpoint URLs.
 */
class WidgetScript implements ArgumentInterface
{
    protected Pool $providerPool;
    protected UrlInterface $url;
    protected LoggerInterface $logger;

    public function __construct(
        Pool $providerPool,
        UrlInterface $url,
        LoggerInterface $logger
    ) {
        $this->providerPool = $providerPool;
        $this->url = $url;
        $this->logger = $logger;
    }

    public function isActive(): bool
    {
        try {
            return $this->providerPool->getActive()->getCode() === Partner::CODE;
        } catch (LocalizedException $e) {
            $this->logger->warning(
                'Avarda ShippingBrokerPartner: cannot resolve active provider, skipping widget script.',
                ['exception' => $e]
            );
            return false;
        }
    }

    /** Base URL for the state endpoint; widget appends /{sessionId}. */
    public function getStateBaseUrl(): string
    {
        return rtrim($this->url->getUrl(PartnerRouter::FRONT_NAME . '/widget/state'), '/');
    }

    /** Base URL for the select endpoint; widget appends /{sessionId}. */
    public function getSelectBaseUrl(): string
    {
        return rtrim($this->url->getUrl(PartnerRouter::FRONT_NAME . '/widget/select'), '/');
    }
}
