<?php

/**
 * @author Avarda Team
 * @copyright Copyright © Avarda. All rights reserved.
 */

declare(strict_types=1);

namespace Avarda\ShippingBrokerPartner\Observer;

use Avarda\ShippingBrokerPartner\Api\SessionRepositoryInterface;
use Avarda\ShippingBrokerPartner\Model\ResourceModel\Session\CollectionFactory;
use Avarda\ShippingBrokerPartner\Model\Session;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * Copies the partner-shipping selection from our session table onto the order
 * shipping address as a JSON snapshot so admin/order tools can render it
 * without re-querying the session.
 */
class SaveSelectionToOrder implements ObserverInterface
{
    public function __construct(
        private readonly CollectionFactory $sessionCollectionFactory
    ) {
    }

    public function execute(Observer $observer): void
    {
        /** @var OrderInterface $order */
        $order = $observer->getEvent()->getOrder();
        if (!$order) {
            return;
        }
        $quoteId = (int) $order->getQuoteId();
        if ($quoteId <= 0) {
            return;
        }

        $collection = $this->sessionCollectionFactory->create();
        $collection->addFieldToFilter('quote_id', $quoteId)
            ->addFieldToFilter('status', Session::STATUS_COMPLETED)
            ->setOrder('updated_at', 'DESC')
            ->setPageSize(1);

        /** @var Session $session */
        $session = $collection->getFirstItem();
        $selected = $session->getSelectedOption();
        if (!$session->getId() || $selected === null || $selected === '') {
            return;
        }

        $shippingAddress = $order->getShippingAddress();
        if ($shippingAddress) {
            $shippingAddress->setData('avarda_shipping_selection', $selected);
        }
    }
}
