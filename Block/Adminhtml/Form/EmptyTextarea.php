<?php

namespace Gigya\GigyaIM\Block\Adminhtml\Form;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class EmptyTextarea extends Field
{
	protected function _getElementHtml(AbstractElement $element)
	{
		$value = $element->getEscapedValue();
		$label = $element->getLabel() ?? 'Value';

		$element->addClass('textarea admin__control-textarea');
		$html = '<textarea id="' . $element->getHtmlId() . '" name="' . $element->getName() . '" '
			. $this->serialize($element->getHtmlAttributes()) . $this->getUiId($element->getType(), $element->getName())
			. ' placeholder="' . ((!empty($value)) ? $label . ' has been entered' : 'Enter ' . $label ) .'"'
			. ' >';
		$html .= ''; /* The textarea is always empty, because the real value is encrypted in any case */
		$html .= "</textarea>";
		$html .= $element->getAfterElementHtml();

		return $html;
	}
}