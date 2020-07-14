<?php

namespace Gigya\GigyaIM\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class EncryptionKeyType implements OptionSourceInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'magento', 'label' => __('Magento')],
            ['value' => 'key_file', 'label' => __('External key file')]
        ];
    }
}
