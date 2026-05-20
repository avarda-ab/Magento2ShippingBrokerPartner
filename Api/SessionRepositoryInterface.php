<?php

/**
 * @author Avarda Team
 * @copyright Copyright © Avarda. All rights reserved.
 */

declare(strict_types=1);

namespace Avarda\ShippingBrokerPartner\Api;

use Avarda\ShippingBrokerPartner\Model\Session;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

interface SessionRepositoryInterface
{
    /**
     * @throws CouldNotSaveException
     */
    public function save(Session $session): Session;

    /**
     * @throws NoSuchEntityException
     */
    public function getBySessionId(string $sessionId): Session;
}
