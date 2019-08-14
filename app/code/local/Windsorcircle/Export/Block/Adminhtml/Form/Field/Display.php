<?php
/**
 * Display field for admin
 *
 * @category  Lyons
 * @package   Windsorcircle_Export
 * @author    Mark Hodge <mhodge@lyonscg.com>
 * @copyright Copyright (c) 2014 Lyons Consulting Group (www.lyonscg.com)
 */

class Windsorcircle_Export_Block_Adminhtml_Form_Field_Display extends Mage_Adminhtml_Block_Template
{
    public function _toHtml()
    {
        $column = $this->getColumn();
        $columnName = $this->getColumnName();
        $inputName = $this->getInputName();

        return '<input type="text" name="' . $inputName . '" value="#{' . $columnName . '}" ' .
            ($column['size'] ? 'size="' . $column['size'] . '"' : '') . ' class="' .
            (isset($column['class']) ? $column['class'] : 'input-text') . '"'.
            (isset($column['style']) ? ' style="'.$column['style'] . '"' : '') . ' readonly />';
    }
}
