<?php

/**
 * @author Avarda Team
 * @copyright Copyright © Avarda. All rights reserved.
 */

declare(strict_types=1);

namespace Avarda\ShippingBrokerPartner\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * Renders the Partner Bearer Secret field with a Generate button.
 */
class PartnerSecret extends Field
{
    protected function _getElementHtml(AbstractElement $element): string
    {
        $init = $this->escapeHtmlAttr((string) json_encode([
            'Avarda_ShippingBrokerPartner/js/generate-secret' => ['field' => '#' . $element->getHtmlId()],
        ]));

        $button = '<button type="button" class="action-default" data-mage-init="' . $init . '">'
            . '<span>' . $this->escapeHtml(__('Generate')) . '</span>'
            . '</button>';

        return parent::_getElementHtml($element) . ' ' . $button;
    }
}
