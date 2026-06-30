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
 * Widget write endpoint for the selected option. No bearer auth — the
 * session_id is the token.
 */
class Select implements ActionInterface, CsrfAwareActionInterface
{
    protected RequestInterface $request;
    protected JsonResultFactory $jsonResultFactory;
    protected SessionManagement $sessionManagement;
    protected Json $serializer;
    protected LoggerInterface $logger;

    public function __construct(
        RequestInterface $request,
        JsonResultFactory $jsonResultFactory,
        SessionManagement $sessionManagement,
        Json $serializer,
        LoggerInterface $logger
    ) {
        $this->request = $request;
        $this->jsonResultFactory = $jsonResultFactory;
        $this->sessionManagement = $sessionManagement;
        $this->serializer = $serializer;
        $this->logger = $logger;
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
            $pickupPointId = isset($payload['pickupPointId']) && (string) $payload['pickupPointId'] !== ''
                ? (string) $payload['pickupPointId']
                : null;
            $response = $this->sessionManagement->recordSelection($sessionId, $shippingMethod, $pickupPointId);
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
