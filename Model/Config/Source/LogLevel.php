<?php

namespace Gigya\GigyaIM\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class LogLevel implements OptionSourceInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => '0', 'label' => __('None')],
            ['value' => '1', 'label' => __('Info')],
            ['value' => '2', 'label' => __('Debug')]
        ];
    }
}
