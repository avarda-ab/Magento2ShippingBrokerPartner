<?php

/**
 * @author Avarda Team
 * @copyright Copyright © Avarda. All rights reserved.
 */

declare(strict_types=1);

namespace Avarda\ShippingBrokerPartner\Model\ResourceModel\Session;

use Avarda\ShippingBrokerPartner\Model\ResourceModel\Session as SessionResource;
use Avarda\ShippingBrokerPartner\Model\Session;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(Session::class, SessionResource::class);
    }
}
