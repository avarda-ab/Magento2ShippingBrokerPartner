<?php

/**
 * @author Avarda Team
 * @copyright Copyright © Avarda. All rights reserved.
 */

declare(strict_types=1);

namespace Avarda\ShippingBrokerPartner\Controller\Widget;

use Avarda\ShippingBrokerPartner\Model\SessionManagement;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory as JsonResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Webapi\Exception as WebapiException;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Public write endpoint the widget POSTs to when the customer picks a shipping
 * option. Avarda's checkout doesn't include the selection in update/complete-
 * session payloads, so the widget has to inform the backend directly. The
 * session id in the URL doubles as the auth token (unguessable UUID v4).
 */
class Select implements ActionInterface, CsrfAwareActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonResultFactory $jsonResultFactory,
        private readonly SessionManagement $sessionManagement,
        private readonly Json $serializer,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): ResultInterface
    {
        $result = $this->jsonResultFactory->create();
        $sessionId = (string) $this->request->getParam('id');
        if ($sessionId === '') {
            $result->setHttpResponseCode(WebapiException::HTTP_BAD_REQUEST);
            $result->setData(['error' => 'Missing session id']);
            return $result;
        }

        try {
            $body = (string) $this->request->getContent();
            $payload = $body !== '' ? $this->serializer->unserialize($body) : [];
            if (!is_array($payload)) {
                $payload = [];
            }
            $shippingMethod = (string) ($payload['shippingMethod'] ?? '');
            $response = $this->sessionManagement->recordSelection($sessionId, $shippingMethod);
            $result->setHttpResponseCode(200);
            $result->setData($response);
            return $result;
        } catch (NoSuchEntityException $e) {
            $result->setHttpResponseCode(WebapiException::HTTP_NOT_FOUND);
            $result->setData(['error' => $e->getMessage()]);
            return $result;
        } catch (LocalizedException $e) {
            $result->setHttpResponseCode(WebapiException::HTTP_BAD_REQUEST);
            $result->setData(['error' => $e->getMessage()]);
            return $result;
        } catch (Throwable $e) {
            $this->logger->error('Avarda partner widget select failure', ['exception' => $e]);
            $result->setHttpResponseCode(WebapiException::HTTP_INTERNAL_ERROR);
            $result->setData(['error' => 'Internal error']);
            return $result;
        }
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
