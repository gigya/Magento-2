<?php

namespace Gigya\GigyaIM\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class DeletionNotFoundBehavior implements OptionSourceInterface
{
	const FAILURE = "failure";
	const SUCCESS = "success";

	/**
	 * @return array
	 */
	public function toOptionArray()
	{
		return [
			['value' => self::FAILURE, 'label' => __('Fail and retry the file in the next run')],
			['value' => self::SUCCESS, 'label' => __('Pass and continue')],
		];
	}
}
