<?php

namespace Gigya\GigyaIM\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class AuthenticationMode implements OptionSourceInterface
{
	/**
	 * @return array
	 */
	public function toOptionArray()
	{
		return [
			['value' => 'user_secret', 'label' => __('User / Secret key pair')],
			['value' => 'user_rsa', 'label' => __('RSA private / public key pair')]
		];
	}
}
