<?php

namespace Gigya\GigyaIM\Model\Config\Source;

use Gigya\GigyaIM\Model\Config;
use Magento\Framework\Data\OptionSourceInterface;

class SessionMode implements OptionSourceInterface
{
    /**
     * Return array of options as value-label pairs
     *
     * @return array Format: array(array('value' => '<value>', 'label' => '<label>'), ...)
     */
    public function toOptionArray()
    {
        return [
            ['value' => Config::SESSION_MODE_FIXED, 'label' => __('Fixed')],
            ['value' => Config::SESSION_MODE_EXTENDED, 'label' => __('Extended')],
            ['value' => Config::SESSION_MODE_BROWSER_INSTANCE, 'label' => __('Browser instance')],
            ['value' => Config::SESSION_MODE_ENDLESS, 'label' => __('Endless')]
        ];
    }
}
