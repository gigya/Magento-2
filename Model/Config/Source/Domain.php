<?php

namespace Gigya\GigyaIM\Model\Config\Source;

class Domain implements \Magento\Framework\Option\ArrayInterface
{
    const DC_US = "us1.gigya.com";
    const DC_EU = "eu1.gigya.com";
    const DC_AU = "au1.gigya.com";
    const DC_RU = "ru1.gigya.com";
    const DC_CN = "cn1.gigya-api.cn";
    /**
     * @return array
     */
    public function toOptionArray()
    {

        return [
            ['value' => self::DC_US, 'label' => __('US')],
            ['value' => self::DC_EU, 'label' => __('EU')],
            ['value' => self::DC_AU, 'label' => __('AU')],
            ['value' => self::DC_RU, 'label' => __('RU')],
            ['value' => self::DC_CN, 'label' => __('CN')],
        ];
    }
}
