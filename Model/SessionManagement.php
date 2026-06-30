<?php

/**
 * @author Avarda Team
 * @copyright Copyright © Avarda. All rights reserved.
 */

declare(strict_types=1);

namespace Avarda\ShippingBrokerPartner\Model;

use Avarda\ShippingBrokerPartner\Api\SessionRepositoryInterface;
use Avarda\ShippingBrokerPartner\Model\Modules\PayloadBuilder;
use Avarda\ShippingBrokerPartner\Model\PickupPoint\ProviderPool as PickupPointPool;
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
 * Backs the four partner shipping endpoints. Writes only our own session row,
 * never the quote.
 */
class SessionManagement
{
    protected PurchaseIdResolver $purchaseIdResolver;
    protected AddressApplier $addressApplier;
    protected RateCollector $rateCollector;
    protected OptionMapper $optionMapper;
    protected PickupPointPool $pickupPointPool;
    protected PayloadBuilder $modulesBuilder;
    protected UuidGenerator $uuidGenerator;
    protected SessionRepositoryInterface $sessionRepository;
    protected SessionFactory $sessionFactory;
    protected Json $serializer;
    protected Config $config;

    public function __construct(
        PurchaseIdResolver $purchaseIdResolver,
        AddressApplier $addressApplier,
        RateCollector $rateCollector,
        OptionMapper $optionMapper,
        PickupPointPool $pickupPointPool,
        PayloadBuilder $modulesBuilder,
        UuidGenerator $uuidGenerator,
        SessionRepositoryInterface $sessionRepository,
        SessionFactory $sessionFactory,
        Json $serializer,
        Config $config
    ) {
        $this->purchaseIdResolver = $purchaseIdResolver;
        $this->addressApplier = $addressApplier;
        $this->rateCollector = $rateCollector;
        $this->optionMapper = $optionMapper;
        $this->pickupPointPool = $pickupPointPool;
        $this->modulesBuilder = $modulesBuilder;
        $this->uuidGenerator = $uuidGenerator;
        $this->sessionRepository = $sessionRepository;
        $this->sessionFactory = $sessionFactory;
        $this->serializer = $serializer;
        $this->config = $config;
    }

    /**
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function create(array $payload): array
    {
        $purchaseId = (string) ($payload['purchaseId'] ?? '');
        $quote = $this->purchaseIdResolver->resolve($purchaseId);
        [$selectedOption, $modulesJson] = $this->buildOptionsForQuote($quote, $payload, null, null);

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
        $selectedPickupPointId = isset($previouslySelected['pickupPoint']['id'])
            ? (string) $previouslySelected['pickupPoint']['id']
            : null;
        if (!empty($payload['selectedShippingOption']['shippingMethod'])) {
            $selectedShippingMethod = (string) $payload['selectedShippingOption']['shippingMethod'];
        }

        [$selectedOption, $modulesJson] = $this->buildOptionsForQuote(
            $quote,
            $payload,
            $selectedShippingMethod,
            $selectedPickupPointId
        );

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
     * Store the customer's widget selection (the spec doesn't carry it on
     * update/complete, so the widget posts it directly). No rate recalc.
     *
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function recordSelection(string $sessionId, string $shippingMethod, ?string $pickupPointId = null): array
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
            'deliveryType'   => (string) ($matchedEntry['deliveryType'] ?? 'delivery'),
            'carrier'        => (string) ($matchedEntry['carrier'] ?? ''),
            'product'        => (string) ($matchedEntry['product'] ?? ''),
            'price'          => (float)  ($matchedEntry['price'] ?? 0.0),
            'currency'       => (string) ($matchedEntry['currency'] ?? ''),
            'carrierCode'    => (string) ($matchedEntry['carrierCode'] ?? ''),
            'methodCode'     => (string) ($matchedEntry['methodCode'] ?? ''),
        ];

        $pickupPoints = is_array($matchedEntry['pickupPoints'] ?? null) ? $matchedEntry['pickupPoints'] : [];
        $selectedPoint = null;
        if ($pickupPoints !== []) {
            $previousPointId = null;
            $previouslySelected = $this->decodeSelected($session);
            if (($previouslySelected['shippingMethod'] ?? null) === $shippingMethod) {
                $previousPointId = isset($previouslySelected['pickupPoint']['id'])
                    ? (string) $previouslySelected['pickupPoint']['id']
                    : null;
            }
            $selectedPoint = $this->resolvePickupPoint($pickupPoints, $pickupPointId ?? $previousPointId);
            if ($pickupPointId !== null && $pickupPointId !== '' && $selectedPoint['id'] !== $pickupPointId) {
                throw new LocalizedException(
                    __('Pickup point "%1" is not available for shipping method "%2".', $pickupPointId, $shippingMethod)
                );
            }
            $selectedShippingOption['pickupPoint'] = $selectedPoint;
        }

        $selectedPointId = $selectedPoint['id'] ?? null;
        $rebuilt = ['options' => array_map(
            static function (array $entry) use ($shippingMethod, $selectedPointId): array {
                $entry['selected'] = ($entry['id'] ?? '') === $shippingMethod;
                if ($entry['selected'] && $selectedPointId !== null) {
                    $entry['selectedPickupPointId'] = $selectedPointId;
                } else {
                    unset($entry['selectedPickupPointId']);
                }
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
     * Recollect rates for the payload. Selection priority: incoming method →
     * first option → null. Keeps the chosen pickup point if still available.
     *
     * @return array{0: array|null, 1: string}
     */
    private function buildOptionsForQuote(
        CartInterface $quote,
        array $payload,
        ?string $selectedShippingMethod,
        ?string $selectedPickupPointId
    ): array {
        $address = $this->addressApplier->apply($quote, $payload['deliveryAddress'] ?? []);
        $rates = $this->rateCollector->collect($address);
        $currency = (string) $quote->getQuoteCurrencyCode();

        $options = [];
        foreach ($rates as $rate) {
            $option = $this->optionMapper->toOption($rate, $currency);
            $provider = $this->pickupPointPool->getForMethod(
                (string) $rate->getCarrier(),
                (string) $rate->getMethod()
            );
            if ($provider !== null) {
                $points = $provider->getPickupPoints((string) $rate->getMethod(), $address);
                if ($points !== []) {
                    $option['deliveryType'] = 'pickup';
                    $option['pickupPoints'] = $points;
                }
            }
            $options[] = $option;
        }

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

        $selectedPoint = null;
        if ($selectedOption !== null && !empty($selectedOption['pickupPoints'])) {
            $selectedPoint = $this->resolvePickupPoint($selectedOption['pickupPoints'], $selectedPickupPointId);
            // Stored selection keeps only the chosen point; the list lives in modules.
            unset($selectedOption['pickupPoints']);
            $selectedOption['pickupPoint'] = $selectedPoint;
        }

        $modulesJson = $this->modulesBuilder->build(
            $options,
            $selectedOption['shippingMethod'] ?? null,
            $selectedPoint['id'] ?? null
        );

        return [$selectedOption, $modulesJson];
    }

    /**
     * @param array[] $points Non-empty list of pickup points
     */
    private function resolvePickupPoint(array $points, ?string $pickupPointId): array
    {
        if ($pickupPointId !== null && $pickupPointId !== '') {
            foreach ($points as $point) {
                if ((string) ($point['id'] ?? '') === $pickupPointId) {
                    return $point;
                }
            }
        }
        return $points[array_key_first($points)];
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
            'selectedShippingOption' => $this->toSpecOption($selectedOption),
            'modules'   => $session->getModules() ?? '',
        ];
        if ($session->getTransportId()) {
            $response['transportId'] = $session->getTransportId();
        }
        return $response;
    }

    /** Reduce to the spec's 6 fields; internal bookkeeping must not leak to Avarda. */
    private function toSpecOption(?array $option): ?array
    {
        if ($option === null) {
            return null;
        }
        return [
            'shippingMethod' => (string) ($option['shippingMethod'] ?? ''),
            'deliveryType'   => (string) ($option['deliveryType'] ?? 'delivery'),
            'carrier'        => (string) ($option['carrier'] ?? ''),
            'product'        => (string) ($option['product'] ?? ''),
            'price'          => (float)  ($option['price'] ?? 0.0),
            'currency'       => (string) ($option['currency'] ?? ''),
        ];
    }

    private function expiresAt(int $storeId): string
    {
        $ttl = $this->config->getSessionTtlSeconds($storeId);
        $expires = (new \DateTimeImmutable())->modify("+{$ttl} seconds");
        return $expires->format(\DateTimeInterface::ATOM);
    }
}
