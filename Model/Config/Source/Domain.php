<?php

namespace Gigya\GigyaIM\Model\Config\Source;

class Domain implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {

        return [
            ['value' => "us1.gigya.com", 'label' => __('US')],
            ['value' => "eu.gigya.com", 'label' => __('EU')],
            ['value' => "au.gigya.com", 'label' => __('AU')],
            ['value' => "ru.gigya.com", 'label' => __('RU')],
        ];
    }
}
