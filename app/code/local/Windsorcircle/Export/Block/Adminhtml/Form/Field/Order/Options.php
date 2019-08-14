<?php
/**
 * Order Input Block in Admin Panel
 *
 * @method getColumn()
 * @method getColumnName()
 * @method getInputName()
 * @method setClass($class)
 * @method setExtraParams($string)
 *
 * @category  WindsorCircle
 * @package   Windsorcircle_Export
 * @author    Mark Hodge <mhodge@lyonscg.com>
 * @copyright Copyright (c) 2016 WindsorCircle (www.windsorcircle.com)
 */

class Windsorcircle_Export_Block_Adminhtml_Form_Field_Order_Options extends Mage_Adminhtml_Block_Template
{
    public function _toHtml()
    {
        $column = $this->getColumn();
        $columnName = $this->getColumnName();
        $inputName = $this->getInputName();

        return '<input type="text" name="' . $inputName . '" value="#{' . $columnName . '}" ' .
        ($column['size'] ? 'size="' . $column['size'] . '"' : '') . ' class="' .
        (isset($column['class']) ? $column['class'] : 'input-text') . '"'.
        (isset($column['style']) ? ' style="'.$column['style'] . '"' : '') . ' />';
    }
}

