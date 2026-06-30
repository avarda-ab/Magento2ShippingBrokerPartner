<?php

/**
 * @author Avarda Team
 * @copyright Copyright © Avarda. All rights reserved.
 */

declare(strict_types=1);

namespace Avarda\ShippingBrokerPartner\Controller\Partner;

use Avarda\ShippingBrokerPartner\Model\Auth\BearerValidator;
use Avarda\ShippingBrokerPartner\Model\SessionManagement;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory as JsonResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

class CompleteSession extends AbstractAction
{
    protected SessionManagement $sessionManagement;

    public function __construct(
        RequestInterface $request,
        JsonResultFactory $jsonResultFactory,
        BearerValidator $bearerValidator,
        Json $serializer,
        LoggerInterface $logger,
        SessionManagement $sessionManagement
    ) {
        parent::__construct($request, $jsonResultFactory, $bearerValidator, $serializer, $logger);
        $this->sessionManagement = $sessionManagement;
    }

    protected function handle(array $payload): array
    {
        $sessionId = (string) $this->request->getParam('id');
        if ($sessionId === '') {
            throw new LocalizedException(__('Missing session id'));
        }
        return $this->sessionManagement->complete($sessionId, $payload);
    }
}
