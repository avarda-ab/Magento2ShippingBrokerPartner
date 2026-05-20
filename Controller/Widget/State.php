<?php

/**
 * @author Avarda Team
 * @copyright Copyright © Avarda. All rights reserved.
 */

declare(strict_types=1);

namespace Avarda\ShippingBrokerPartner\Controller\Widget;

use Avarda\ShippingBrokerPartner\Api\SessionRepositoryInterface;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory as JsonResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Webapi\Exception as WebapiException;
use Psr\Log\LoggerInterface;

/**
 * Public read endpoint for the storefront widget — no bearer auth, since the
 * session_id itself is the unguessable token. Returns the same envelope shape
 * the partner endpoints return so the widget can update without branching.
 */
class State implements ActionInterface, CsrfAwareActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonResultFactory $jsonResultFactory,
        private readonly SessionRepositoryInterface $sessionRepository,
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
            $session = $this->sessionRepository->getBySessionId($sessionId);
        } catch (NoSuchEntityException $e) {
            $result->setHttpResponseCode(WebapiException::HTTP_NOT_FOUND);
            $result->setData(['error' => $e->getMessage()]);
            return $result;
        } catch (\Throwable $e) {
            $this->logger->error('Avarda partner widget state failure', ['exception' => $e]);
            $result->setHttpResponseCode(WebapiException::HTTP_INTERNAL_ERROR);
            $result->setData(['error' => 'Internal error']);
            return $result;
        }

        $result->setHttpResponseCode(200);
        $result->setData([
            'id'        => $session->getSessionId(),
            'status'    => $session->getStatus(),
            'expiresAt' => $session->getExpiresAt(),
            'modules'   => $session->getModules() ?? '',
        ]);
        return $result;
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
