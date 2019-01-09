<?php

namespace Gigya\GigyaIM\Block\Widget;

use Gigya\GigyaIM\Model\Config as GigyaConfig;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\View\Element\Template;
use Magento\Widget\Block\BlockInterface;

class CustomScreenSet extends Template implements BlockInterface
{
	protected $_template = "gigya_custom_screenset.phtml";
	protected $serializer;
	protected $config;

	public function __construct(
		Template\Context $context,
		SerializerInterface $serializer,
		GigyaConfig $config,
		array $data = []
	) {
		parent::__construct($context, $data);

		$this->serializer = $serializer;
		$this->config = $config;
	}

	/**
	 * @param $desktopScreenSet
	 *
	 * @return array|false
	 */
	public function getScreenSetConfig($desktopScreenSet) {
		$screenSets = $this->serializer->unserialize($this->config->getCustomScreensets());
		foreach ($screenSets as $screenSet) {
			if ($screenSet['desktop_screen'] == $desktopScreenSet) {
				return $screenSet;
			}
		}

		return false;
	}
}