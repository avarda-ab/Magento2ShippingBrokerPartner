<?php

/**
 * @author Avarda Team
 * @copyright Copyright © Avarda. All rights reserved.
 */

declare(strict_types=1);

namespace Avarda\ShippingBrokerPartner\Model\Quote;

use Avarda\Checkout3\Api\QuotePaymentManagementInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\PaymentException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;

/**
 * Resolves a quote from an Avarda Checkout3 purchaseId.
 */
class PurchaseIdResolver
{
    protected QuotePaymentManagementInterface $quotePaymentManagement;
    protected CartRepositoryInterface $cartRepository;

    public function __construct(
        QuotePaymentManagementInterface $quotePaymentManagement,
        CartRepositoryInterface $cartRepository
    ) {
        $this->quotePaymentManagement = $quotePaymentManagement;
        $this->cartRepository = $cartRepository;
    }

    /**
     * @throws NoSuchEntityException|PaymentException
     */
    public function resolve(string $purchaseId): CartInterface
    {
        if ($purchaseId === '') {
            throw new NoSuchEntityException(__('Missing purchaseId'));
        }
        $quoteId = $this->quotePaymentManagement->getQuoteIdByPurchaseId($purchaseId);
        return $this->cartRepository->get((int) $quoteId);
    }
}
