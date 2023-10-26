<?php

namespace Gigya\GigyaIM\Block\Adminhtml\System\Config\View;

use Magento\Framework\View\Element\AbstractBlock;

class Checkbox extends AbstractBlock
{
    protected function _toHtml(): string
    {
        $elementId   = $this->getInputId();
        $elementName = $this->getInputName();
        $columnName  = $this->getColumnName();
        $column      = $this->getColumn();

        return '<input type="checkbox" id="' . $elementId . '" ' .
               'name="' . $elementName . '" data-column="' . $columnName . '"' .
               'value="1" ' .
               ($column['size'] ? 'size="' . $column['size'] . '"' : '') .
               ' class="' . ($column['class'] ?? 'input-text') . '"' .
               (isset($column['style']) ? ' style="' . $column['style'] . '"' : '') . '/>';
    }
}
