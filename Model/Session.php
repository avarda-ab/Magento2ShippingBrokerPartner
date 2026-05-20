<?php

/**
 * @author Avarda Team
 * @copyright Copyright © Avarda. All rights reserved.
 */

declare(strict_types=1);

namespace Avarda\ShippingBrokerPartner\Model;

use Avarda\ShippingBrokerPartner\Model\ResourceModel\Session as SessionResource;
use Magento\Framework\Model\AbstractModel;

/**
 * @method string|null getSessionId()
 * @method self setSessionId(string $value)
 * @method string|null getPurchaseId()
 * @method self setPurchaseId(string $value)
 * @method int|null getQuoteId()
 * @method self setQuoteId(?int $value)
 * @method string|null getStatus()
 * @method self setStatus(string $value)
 * @method string|null getTransportId()
 * @method self setTransportId(?string $value)
 * @method string|null getSelectedOption()
 * @method self setSelectedOption(?string $value)
 * @method string|null getDeliveryAddress()
 * @method self setDeliveryAddress(?string $value)
 * @method string|null getModules()
 * @method self setModules(?string $value)
 * @method string|null getExpiresAt()
 * @method self setExpiresAt(string $value)
 */
class Session extends AbstractModel
{
    public const string STATUS_ACTIVE = 'ACTIVE';
    public const string STATUS_COMPLETED = 'COMPLETED';

    protected function _construct(): void
    {
        $this->_init(SessionResource::class);
    }
}
