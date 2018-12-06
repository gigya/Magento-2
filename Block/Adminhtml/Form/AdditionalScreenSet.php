<?php

namespace Gigya\GigyaIM\Block\Adminhtml\Form;

use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;

class AdditionalScreenSet extends AbstractFieldArray
{
	/**
	 * {@inheritdoc}
	 */
	protected function _prepareToRender() {
		$this->addColumn('widget', ['label' => __('Widget')]);
		$this->addColumn('screenset', ['label' => __('Screen-Set'), 'class' => 'required-entry']);
		$this->_addAfter       = false;
		$this->_addButtonLabel = __('Add Screen-Set');
	}
}