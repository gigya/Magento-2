<?php

namespace Gigya\GigyaIM\Model\Config\Source;

class Keytype implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {

        return [
            ['value' => "env", 'label' => __('Env')],
            ['value' => "file", 'label' => __('File')]
        ];
    }
}
