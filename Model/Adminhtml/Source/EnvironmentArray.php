<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Wizpay\Wizpay\Model\Adminhtml\Source;

use Magento\Payment\Model\Method\AbstractMethod;

/**
 * Class EnvironmentArray
 */
class EnvironmentArray implements \Magento\Framework\Option\ArrayInterface // phpcs:ignore
{
    
    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => 0,
                'label' => __('Production')
            ],
            [
                'value' => 1,
                'label' => __('Sandbox')
            ]
        ];
    }
}
