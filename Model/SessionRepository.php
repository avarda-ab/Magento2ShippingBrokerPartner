<?php

/**
 * @author Avarda Team
 * @copyright Copyright © Avarda. All rights reserved.
 */

declare(strict_types=1);

namespace Avarda\ShippingBrokerPartner\Model;

use Avarda\ShippingBrokerPartner\Api\SessionRepositoryInterface;
use Avarda\ShippingBrokerPartner\Model\ResourceModel\Session as SessionResource;
use Avarda\ShippingBrokerPartner\Model\ResourceModel\Session\CollectionFactory;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

class SessionRepository implements SessionRepositoryInterface
{
    public function __construct(
        private readonly SessionResource $resource,
        private readonly SessionFactory $sessionFactory,
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    public function save(Session $session): Session
    {
        try {
            $this->resource->save($session);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(
                __('Could not save Avarda partner shipping session: %1', $e->getMessage()),
                $e
            );
        }
        return $session;
    }

    public function getBySessionId(string $sessionId): Session
    {
        /** @var \Avarda\ShippingBrokerPartner\Model\ResourceModel\Session\Collection $collection */
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('session_id', $sessionId)->setPageSize(1);
        $session = $collection->getFirstItem();
        if (!$session->getId()) {
            throw new NoSuchEntityException(
                __('Avarda partner shipping session "%1" not found.', $sessionId)
            );
        }
        return $session;
    }
}
