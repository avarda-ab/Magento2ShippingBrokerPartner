<?php

/**
 * @author Avarda Team
 * @copyright Copyright © Avarda. All rights reserved.
 */

declare(strict_types=1);

namespace Avarda\ShippingBrokerPartner\Model;

use Avarda\ShippingBrokerPartner\Api\SessionRepositoryInterface;
use Avarda\ShippingBrokerPartner\Model\Modules\PayloadBuilder;
use Avarda\ShippingBrokerPartner\Model\Quote\AddressApplier;
use Avarda\ShippingBrokerPartner\Model\Quote\PurchaseIdResolver;
use Avarda\ShippingBrokerPartner\Model\Rate\Collector as RateCollector;
use Avarda\ShippingBrokerPartner\Model\Rate\OptionMapper;
use Avarda\ShippingBrokerPartner\Model\Session\UuidGenerator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Api\Data\CartInterface;

/**
 * Orchestrates the four partner shipping endpoints. Operations don't persist
 * the quote — only the partner Session row in our own table — so back-to-back
 * update-session calls don't fight Magento's own quote save flow.
 */
class SessionManagement
{
    public function __construct(
        private readonly PurchaseIdResolver $purchaseIdResolver,
        private readonly AddressApplier $addressApplier,
        private readonly RateCollector $rateCollector,
        private readonly OptionMapper $optionMapper,
        private readonly PayloadBuilder $modulesBuilder,
        private readonly UuidGenerator $uuidGenerator,
        private readonly SessionRepositoryInterface $sessionRepository,
        private readonly SessionFactory $sessionFactory,
        private readonly Json $serializer,
        private readonly Config $config
    ) {
    }

    /**
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function create(array $payload): array
    {
        $purchaseId = (string) ($payload['purchaseId'] ?? '');
        $quote = $this->purchaseIdResolver->resolve($purchaseId);
        [$selectedOption, $modulesJson] = $this->buildOptionsForQuote($quote, $payload, null);

        $session = $this->sessionFactory->create();
        $session->setSessionId($this->uuidGenerator->generate());
        $session->setPurchaseId($purchaseId);
        $session->setQuoteId((int) $quote->getId());
        $session->setStatus(Session::STATUS_ACTIVE);
        $session->setSelectedOption($selectedOption !== null ? $this->serializer->serialize($selectedOption) : null);
        $session->setDeliveryAddress($this->serializer->serialize($payload['deliveryAddress'] ?? []));
        $session->setModules($modulesJson);
        $session->setExpiresAt($this->expiresAt((int) $quote->getStoreId()));
        $this->sessionRepository->save($session);

        return $this->buildResponse($session, $selectedOption);
    }

    /**
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function update(string $sessionId, array $payload): array
    {
        $session = $this->sessionRepository->getBySessionId($sessionId);
        if ($session->getStatus() === Session::STATUS_COMPLETED) {
            throw new LocalizedException(__('Session %1 is already completed.', $sessionId));
        }

        $quote = $this->purchaseIdResolver->resolve((string) $session->getPurchaseId());
        $previouslySelected = $this->decodeSelected($session);
        $selectedShippingMethod = $previouslySelected['shippingMethod'] ?? null;
        if (!empty($payload['selectedShippingOption']['shippingMethod'])) {
            $selectedShippingMethod = (string) $payload['selectedShippingOption']['shippingMethod'];
        }

        [$selectedOption, $modulesJson] = $this->buildOptionsForQuote($quote, $payload, $selectedShippingMethod);

        $session->setSelectedOption($selectedOption !== null ? $this->serializer->serialize($selectedOption) : null);
        $session->setDeliveryAddress($this->serializer->serialize($payload['deliveryAddress'] ?? []));
        $session->setModules($modulesJson);
        $session->setExpiresAt($this->expiresAt((int) $quote->getStoreId()));
        $this->sessionRepository->save($session);

        return $this->buildResponse($session, $selectedOption);
    }

    /**
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function complete(string $sessionId, array $payload): array
    {
        $session = $this->sessionRepository->getBySessionId($sessionId);
        if ($session->getStatus() === Session::STATUS_COMPLETED) {
            return $this->buildResponse($session, $this->decodeSelected($session));
        }

        if (!empty($payload['deliveryAddress'])) {
            $session->setDeliveryAddress($this->serializer->serialize($payload['deliveryAddress']));
        }

        $session->setStatus(Session::STATUS_COMPLETED);
        $session->setTransportId($this->uuidGenerator->generate());
        $this->sessionRepository->save($session);

        return $this->buildResponse($session, $this->decodeSelected($session));
    }

    /**
     * @throws NoSuchEntityException
     */
    public function get(string $sessionId): array
    {
        $session = $this->sessionRepository->getBySessionId($sessionId);
        return $this->buildResponse($session, $this->decodeSelected($session));
    }

    /**
     * Records a customer-driven selection from the widget without recalculating
     * rates. The Partner Shipping spec doesn't carry the selected option in
     * subsequent update/complete-session calls, so the widget must inform us
     * directly — otherwise the backend would return whatever default we picked
     * on create-session.
     *
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function recordSelection(string $sessionId, string $shippingMethod): array
    {
        if ($shippingMethod === '') {
            throw new LocalizedException(__('Missing shippingMethod'));
        }

        $session = $this->sessionRepository->getBySessionId($sessionId);
        if ($session->getStatus() === Session::STATUS_COMPLETED) {
            throw new LocalizedException(__('Session %1 is already completed.', $sessionId));
        }

        $modules = $this->decodeModules($session);
        $options = is_array($modules['options'] ?? null) ? $modules['options'] : [];

        $matchedEntry = null;
        foreach ($options as $option) {
            if (($option['id'] ?? '') === $shippingMethod) {
                $matchedEntry = $option;
                break;
            }
        }
        if ($matchedEntry === null) {
            throw new LocalizedException(
                __('Shipping method "%1" is not available in session %2.', $shippingMethod, $sessionId)
            );
        }

        $selectedShippingOption = [
            'shippingMethod' => (string) $matchedEntry['id'],
            'deliveryType'   => 'delivery',
            'carrier'        => (string) ($matchedEntry['carrier'] ?? ''),
            'product'        => (string) ($matchedEntry['product'] ?? ''),
            'price'          => (float)  ($matchedEntry['price'] ?? 0.0),
            'currency'       => (string) ($matchedEntry['currency'] ?? ''),
        ];

        $rebuilt = ['options' => array_map(
            static function (array $entry) use ($shippingMethod): array {
                $entry['selected'] = ($entry['id'] ?? '') === $shippingMethod;
                return $entry;
            },
            $options
        )];

        $session->setSelectedOption($this->serializer->serialize($selectedShippingOption));
        $session->setModules($this->serializer->serialize($rebuilt));
        $this->sessionRepository->save($session);

        return $this->buildResponse($session, $selectedShippingOption);
    }

    private function decodeModules(Session $session): array
    {
        $raw = $session->getModules();
        if ($raw === null || $raw === '') {
            return [];
        }
        $decoded = $this->serializer->unserialize($raw);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Recalculates rates against the supplied payload. Returns the
     * [selectedOption, modulesJson] tuple. Selection priority: explicit
     * incoming selected method → first option → null (no rates available).
     *
     * @return array{0: array|null, 1: string}
     */
    private function buildOptionsForQuote(
        CartInterface $quote,
        array $payload,
        ?string $selectedShippingMethod
    ): array {
        $address = $this->addressApplier->apply($quote, $payload['deliveryAddress'] ?? []);
        $rates = $this->rateCollector->collect($quote, $address);
        $currency = (string) $quote->getQuoteCurrencyCode();
        $options = $this->optionMapper->toOptions($rates, $currency);

        $selectedOption = null;
        if ($options !== []) {
            if ($selectedShippingMethod !== null) {
                foreach ($options as $option) {
                    if ($option['shippingMethod'] === $selectedShippingMethod) {
                        $selectedOption = $option;
                        break;
                    }
                }
            }
            if ($selectedOption === null) {
                $selectedOption = $options[0];
            }
        }

        $modulesJson = $this->modulesBuilder->build(
            $options,
            $selectedOption['shippingMethod'] ?? null
        );

        return [$selectedOption, $modulesJson];
    }

    private function decodeSelected(Session $session): ?array
    {
        $raw = $session->getSelectedOption();
        if ($raw === null || $raw === '') {
            return null;
        }
        $decoded = $this->serializer->unserialize($raw);
        return is_array($decoded) ? $decoded : null;
    }

    private function buildResponse(Session $session, ?array $selectedOption): array
    {
        $response = [
            'id'        => $session->getSessionId(),
            'status'    => $session->getStatus(),
            'expiresAt' => $session->getExpiresAt(),
            'selectedShippingOption' => $selectedOption,
            'modules'   => $session->getModules() ?? '',
        ];
        if ($session->getTransportId()) {
            $response['transportId'] = $session->getTransportId();
        }
        return $response;
    }

    private function expiresAt(int $storeId): string
    {
        $ttl = $this->config->getSessionTtlSeconds($storeId);
        $expires = (new \DateTimeImmutable())->modify("+{$ttl} seconds");
        return $expires->format(\DateTimeInterface::ATOM);
    }
}
