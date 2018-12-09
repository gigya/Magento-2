<?php

namespace Gigya\GigyaIM\Block\Widget;

use Magento\Framework\View\Element\Template;
use Magento\Widget\Block\BlockInterface;

class CustomScreenSet extends Template implements BlockInterface
{
	protected $_template = "gigya_custom_screenset.phtml";

	public function __construct(Template\Context $context, array $data = [])
	{
		parent::__construct($context, $data);
	}
}