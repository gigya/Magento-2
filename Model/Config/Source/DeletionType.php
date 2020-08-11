<?php

namespace Gigya\GigyaIM\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class DeletionType implements OptionSourceInterface
{
	const HARD_DELETE = "hard_delete";
	const SOFT_DELETE = "soft_delete";

	/**
	 * @return array
	 */
	public function toOptionArray()
	{
		return [
			['value' => self::HARD_DELETE, 'label' => __('Full user deletion')],
			['value' => self::SOFT_DELETE, 'label' => __('Tag deleted user')],
		];
	}
}
