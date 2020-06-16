<?php

namespace Gigya\GigyaIM\Block\Adminhtml\Form;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class EmptyTextarea extends Field
{
	protected function _getElementHtml(AbstractElement $element)
	{
		$element->addClass('textarea admin__control-textarea');
		$html = '<textarea id="' . $element->getHtmlId() . '" name="' . $element->getName() . '" '
			. $this->serialize($element->getHtmlAttributes()) . $this->getUiId($element->getType(), $element->getName()) . ' >';
		$html .= '';
		$html .= "</textarea>";
		$html .= $element->getAfterElementHtml();

		return $html;
	}
}