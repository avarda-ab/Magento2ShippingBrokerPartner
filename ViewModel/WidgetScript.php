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
 * Backs the partner widget include block: tells the template whether to render
 * (only when the active provider is `partner`) and where the widget can fetch
 * session state from.
 */
class WidgetScript implements ArgumentInterface
{
    public function __construct(
        private readonly Pool $providerPool,
        private readonly UrlInterface $url,
        private readonly LoggerInterface $logger
    ) {
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

    /**
     * Base URL the widget calls to refresh session state. The session id is
     * appended client-side: `${stateBaseUrl}/${sessionId}`.
     */
    public function getStateBaseUrl(): string
    {
        return rtrim($this->url->getUrl(PartnerRouter::FRONT_NAME . '/widget/state'), '/');
    }

    /**
     * Base URL the widget POSTs to when the customer changes the selected
     * shipping option. The session id is appended client-side:
     * `${selectBaseUrl}/${sessionId}`.
     */
    public function getSelectBaseUrl(): string
    {
        return rtrim($this->url->getUrl(PartnerRouter::FRONT_NAME . '/widget/select'), '/');
    }
}
