<?php

/**
 * @author Avarda Team
 * @copyright Copyright © Avarda. All rights reserved.
 */

declare(strict_types=1);

namespace Avarda\ShippingBrokerPartner\Controller\Partner;

use Avarda\ShippingBrokerPartner\Model\Auth\BearerValidator;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json as JsonResult;
use Magento\Framework\Controller\Result\JsonFactory as JsonResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Webapi\Exception as WebapiException;
use Psr\Log\LoggerInterface;

/**
 * Bearer auth + JSON envelope shared by the partner endpoints. CSRF is off
 * (server-to-server calls, authenticated by the Bearer secret).
 */
abstract class AbstractAction implements ActionInterface, CsrfAwareActionInterface
{
    protected RequestInterface $request;
    protected JsonResultFactory $jsonResultFactory;
    protected BearerValidator $bearerValidator;
    protected Json $serializer;
    protected LoggerInterface $logger;

    public function __construct(
        RequestInterface $request,
        JsonResultFactory $jsonResultFactory,
        BearerValidator $bearerValidator,
        Json $serializer,
        LoggerInterface $logger
    ) {
        $this->request = $request;
        $this->jsonResultFactory = $jsonResultFactory;
        $this->bearerValidator = $bearerValidator;
        $this->serializer = $serializer;
        $this->logger = $logger;
    }

    public function execute(): ResultInterface
    {
        if (!$this->bearerValidator->validate($this->request)) {
            return $this->error(WebapiException::HTTP_UNAUTHORIZED, 'Unauthorized');
        }
        try {
            $payload = $this->readPayload();
            $data = $this->handle($payload);
            return $this->success($data);
        } catch (NoSuchEntityException $e) {
            return $this->error(WebapiException::HTTP_NOT_FOUND, $e->getMessage());
        } catch (LocalizedException $e) {
            return $this->error(WebapiException::HTTP_BAD_REQUEST, $e->getMessage());
        } catch (\Throwable $e) {
            $this->logger->error('Avarda partner shipping endpoint failure', ['exception' => $e]);
            return $this->error(WebapiException::HTTP_INTERNAL_ERROR, 'Internal error');
        }
    }

    /**
     * Endpoint logic: decoded request body in, response body out.
     *
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    abstract protected function handle(array $payload): array;

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    private function readPayload(): array
    {
        $body = (string) $this->request->getContent();
        if ($body === '') {
            return [];
        }
        $decoded = $this->serializer->unserialize($body);
        return is_array($decoded) ? $decoded : [];
    }

    protected function success(array $data): JsonResult
    {
        $result = $this->jsonResultFactory->create();
        $result->setHttpResponseCode(200);
        $result->setData($data);
        return $result;
    }

    protected function error(int $status, string $message): JsonResult
    {
        $result = $this->jsonResultFactory->create();
        $result->setHttpResponseCode($status);
        $result->setData(['error' => $message]);
        return $result;
    }
}
