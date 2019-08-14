<?php
/**
 * Version Block class
 *
 * @category   Lyons
 * @package    Windsorcircle_Export
 * @copyright  Copyright (c) 2014 Lyons Consulting Group (www.lyonscg.com)
 * @author     Mark Hodge (mhodge@lyonscg.com)
 */

class Windsorcircle_Export_Block_Adminhtml_Version extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        return (string) Mage::helper('windsorcircle_export')->getExtensionVersion();
    }
}
