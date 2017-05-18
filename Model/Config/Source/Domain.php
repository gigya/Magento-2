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
            ['value' => "eu1.gigya.com", 'label' => __('EU')],
            ['value' => "au1.gigya.com", 'label' => __('AU')],
            ['value' => "ru1.gigya.com", 'label' => __('RU')],
            ['value' => "cn1.gigya-api.cn", 'label' => __('CN')],
        ];
    }
}
