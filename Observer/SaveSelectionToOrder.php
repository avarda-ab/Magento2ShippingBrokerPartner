<?php

/**
 * @author Avarda Team
 * @copyright Copyright © Avarda. All rights reserved.
 */

declare(strict_types=1);

namespace Avarda\ShippingBrokerPartner\Observer;

use Avarda\ShippingBrokerPartner\Model\PickupPoint\ProviderPool as PickupPointPool;
use Avarda\ShippingBrokerPartner\Model\ResourceModel\Session\CollectionFactory;
use Avarda\ShippingBrokerPartner\Model\Session;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Api\Data\OrderInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * On order save: snapshot the selection, swap the broker placeholder shipping
 * method/description for the real one, and let the carrier provider persist its
 * pickup point. Guarded on the placeholder so it runs once at placement.
 */
class SaveSelectionToOrder implements ObserverInterface
{
    /** Broker placeholder method, replaced with the real Magento method. */
    protected const PLACEHOLDER_SHIPPING_METHOD = 'avarda_shipping_broker';

    protected CollectionFactory $sessionCollectionFactory;
    protected PickupPointPool $pickupPointPool;
    protected Json $serializer;
    protected LoggerInterface $logger;

    public function __construct(
        CollectionFactory $sessionCollectionFactory,
        PickupPointPool $pickupPointPool,
        Json $serializer,
        LoggerInterface $logger
    ) {
        $this->sessionCollectionFactory = $sessionCollectionFactory;
        $this->pickupPointPool = $pickupPointPool;
        $this->serializer = $serializer;
        $this->logger = $logger;
    }

    public function execute(Observer $observer): void
    {
        /** @var OrderInterface $order */
        $order = $observer->getEvent()->getOrder();
        if (!$order) {
            return;
        }
        // Only while still on the placeholder, so we don't clobber later changes.
        if ($order->getShippingMethod() !== self::PLACEHOLDER_SHIPPING_METHOD) {
            return;
        }
        $quoteId = (int) $order->getQuoteId();
        if ($quoteId <= 0) {
            return;
        }

        $selected = $this->loadSelection($quoteId);
        if ($selected === null) {
            return;
        }

        $shippingAddress = $order->getShippingAddress();
        if ($shippingAddress) {
            $shippingAddress->setData('avarda_shipping_selection', $selected);
        }

        $shippingData = $this->decode($selected);
        if ($shippingData === null) {
            return;
        }

        $this->applyShippingMethod($order, $shippingData);
    }

    private function loadSelection(int $quoteId): ?string
    {
        $collection = $this->sessionCollectionFactory->create();
        $collection->addFieldToFilter('quote_id', $quoteId)
            ->addFieldToFilter('status', Session::STATUS_COMPLETED)
            ->setOrder('updated_at', 'DESC')
            ->setPageSize(1);

        /** @var Session $session */
        $session = $collection->getFirstItem();
        $selected = $session->getSelectedOption();
        if (!$session->getId() || $selected === null || $selected === '') {
            return null;
        }
        return $selected;
    }

    private function applyShippingMethod(OrderInterface $order, array $shippingData): void
    {
        $magentoMethod = (string) ($shippingData['shippingMethod'] ?? '');
        if ($magentoMethod !== '') {
            $order->setShippingMethod($magentoMethod);
            $order->setShippingDescription($this->buildShippingDescription($shippingData));
        }

        $provider = $this->pickupPointPool->getForMethod(
            (string) ($shippingData['carrierCode'] ?? ''),
            (string) ($shippingData['methodCode'] ?? '')
        );
        if ($provider === null) {
            return;
        }
        $provider->applyToOrder($order, $shippingData);
    }

    /** "Carrier - Product (Pickup point)". */
    private function buildShippingDescription(array $shippingData): string
    {
        $parts = array_filter(
            [
                trim((string) ($shippingData['carrier'] ?? '')),
                trim((string) ($shippingData['product'] ?? '')),
            ],
            static fn (string $part): bool => $part !== ''
        );
        $description = implode(' - ', $parts);

        $pointName = trim((string) ($shippingData['pickupPoint']['name'] ?? ''));
        if ($pointName !== '') {
            $description = $description === '' ? $pointName : $description . ' (' . $pointName . ')';
        }

        return $description !== '' ? $description : (string) ($shippingData['shippingMethod'] ?? '');
    }

    private function decode(string $selected): ?array
    {
        try {
            $decoded = $this->serializer->unserialize($selected);
        } catch (Throwable $e) {
            $this->logger->warning(
                'Avarda ShippingBrokerPartner: could not decode stored selection JSON.',
                ['exception' => $e]
            );
            return null;
        }
        return is_array($decoded) ? $decoded : null;
    }
}
