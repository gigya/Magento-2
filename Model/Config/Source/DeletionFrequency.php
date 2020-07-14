<?php

namespace Gigya\GigyaIM\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class DeletionFrequency implements OptionSourceInterface
{
	/**
	 * @return array
	 */
	public function toOptionArray()
	{
		return [
			['value' => '* * * * *', 'label' => __('Every minute')],
			['value' => '*/5 * * * *', 'label' => __('Every 5 minutes')],
			['value' => '*/30 * * * *', 'label' => __('Every 30 minutes')],
			['value' => '0 * * * *', 'label' => __('Every hour')],
			['value' => '0 0 * * *', 'label' => __('Every day')],
			['value' => '0 0 * * 0', 'label' => __('Every week')],
			['value' => '0 0 1 * *', 'label' => __('Every month')],
		];
	}
}