<?php

namespace Gigya\GigyaIM\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Domain implements OptionSourceInterface
{
    const string DC_US = "us1.gigya.com";
    const string DC_EU = "eu1.gigya.com";
    const string DC_AU = "au1.gigya.com";
    const string DC_CN = "cn1.sapcdm.cn";
    const string OTHER = "other";

    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => self::DC_US, 'label' => __('US')],
            ['value' => self::DC_EU, 'label' => __('EU')],
            ['value' => self::DC_AU, 'label' => __('AU')],
            ['value' => self::DC_CN, 'label' => __('CN')],
            ['value' => self::OTHER, 'label' => __('Other')],
        ];
    }
}
