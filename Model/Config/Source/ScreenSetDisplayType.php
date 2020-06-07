<?php

namespace Gigya\GigyaIM\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class ScreenSetDisplayType implements OptionSourceInterface
{
	public function toOptionArray()
	{
		return [
			['value' => 'embed', 'label' => __('Embed')],
			['value' => 'popup', 'label' => __('Popup')],
		];
	}
}