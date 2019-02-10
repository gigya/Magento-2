<?php

namespace Gigya\GigyaIM\Block\Adminhtml\Form;

use Gigya\GigyaIM\Block\Adminhtml\System\Config\View\Checkbox;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;

class AdditionalScreenSet extends AbstractFieldArray
{
	private $_checkboxRenderer;

	public function __construct(Context $context, Checkbox $checkboxRenderer) {
		$this->_checkboxRenderer = $checkboxRenderer;

		parent::__construct($context);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function _prepareToRender() {
		$this->addColumn('desktop_screen', ['label' => __('Screen-Set ID'), 'class' => 'required-entry', 'renderer' => false]);
		$this->addColumn('mobile_screen', ['label' => __('Mobile Screen-Set ID'), 'renderer' => false]);
		$this->addColumn('is_syncable', ['label' => __('Sync Data'), 'renderer' => $this->_checkboxRenderer]);

		$this->_addAfter       = false;
		$this->_addButtonLabel = __('Add Screen-Set');
	}
}