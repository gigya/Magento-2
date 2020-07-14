<?php

namespace Gigya\GigyaIM\Model\Config\Source;

use Gigya\GigyaIM\Model\Config as GigyaConfig;
use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Serialize\SerializerInterface;

class MobileScreenSetList implements OptionSourceInterface
{
	protected $config;
	protected $serializer;

	public function __construct(
		GigyaConfig $gigyaConfig,
		SerializerInterface $serializer
	) {
		$this->config     = $gigyaConfig;
		$this->serializer = $serializer;
	}

	public function toOptionArray() {
		$screensetConfig = $this->config->getCustomScreensets();

		$screensets = array();

		if (!empty($screensetConfig))
		{
			foreach ($this->serializer->unserialize($screensetConfig) as $screenset)
			{
				if (!empty($screenset['mobile_screen']))
					$screensets[] = ['value' => $screenset['mobile_screen'], 'label' => $screenset['mobile_screen']];
			}
		}

		return $screensets;
	}
}