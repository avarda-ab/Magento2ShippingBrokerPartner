<?php

/**
 * @author Avarda Team
 * @copyright Copyright © Avarda. All rights reserved.
 */

declare(strict_types=1);

namespace Avarda\ShippingBrokerPartner\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Session extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('avarda_shipping_broker_partner_session', 'entity_id');
    }
}
