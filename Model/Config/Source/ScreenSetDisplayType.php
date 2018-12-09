<?php

namespace Gigya\GigyaIM\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class ScreenSetDisplayType implements ArrayInterface
{
	public function toOptionArray()
	{
		return [
			['value' => 'embed', 'label' => __('Embed')],
			['value' => 'popup', 'label' => __('Popup')],
		];
	}
}