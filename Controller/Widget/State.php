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
 * Widget read endpoint. No bearer auth — the session_id is the token.
 */
class State implements ActionInterface, CsrfAwareActionInterface
{
    protected RequestInterface $request;
    protected JsonResultFactory $jsonResultFactory;
    protected SessionRepositoryInterface $sessionRepository;
    protected LoggerInterface $logger;

    public function __construct(
        RequestInterface $request,
        JsonResultFactory $jsonResultFactory,
        SessionRepositoryInterface $sessionRepository,
        LoggerInterface $logger
    ) {
        $this->request = $request;
        $this->jsonResultFactory = $jsonResultFactory;
        $this->sessionRepository = $sessionRepository;
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
