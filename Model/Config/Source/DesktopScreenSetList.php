<?php

namespace Gigya\GigyaIM\Model\Config\Source;

use Gigya\GigyaIM\Model\Config as GigyaConfig;
use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Serialize\SerializerInterface;

class DesktopScreenSetList implements OptionSourceInterface
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
				$screensets[] = ['value' => $screenset['desktop_screen'], 'label' => $screenset['desktop_screen']];
			}
		}

		return $screensets;
	}
}