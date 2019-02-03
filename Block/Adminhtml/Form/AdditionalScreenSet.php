<?php

namespace Gigya\GigyaIM\Block\Adminhtml\Form;

use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;

class AdditionalScreenSet extends AbstractFieldArray
{
	/**
	 * {@inheritdoc}
	 */
	protected function _prepareToRender() {
		$this->addColumn('desktop_screen', ['label' => __('Desktop Screen-Set ID'), 'class' => 'required-entry']);
		$this->addColumn('mobile_screen', ['label' => __('Mobile Screen-Set ID')]);
		$this->_addAfter       = false;
		$this->_addButtonLabel = __('Add Screen-Set');
	}
}