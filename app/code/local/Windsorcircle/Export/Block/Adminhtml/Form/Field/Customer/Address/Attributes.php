<?php
/**
 * Customer Address Attributes Class
 *
 * @category  Lyons
 * @package   Windsorcircle_Export
 * @author    Mark Hodge <mhodge@lyonscg.com>
 * @copyright Copyright (c) 2014 Lyons Consulting Group (www.lyonscg.com)
 */ 

class Windsorcircle_Export_Block_Adminhtml_Form_Field_Customer_Address_Attributes
    extends Mage_Adminhtml_Block_System_Config_Form_Field_Array_Abstract
{
    /**
     * @var
     */
    protected $_customerAddressRenderer;

    /**
     * @var
     */
    protected $_customerAddressDisplayRenderer;

    /**
     * Constructor
     */
    public function __construct()
    {
        $version = explode('.', Mage::getVersion());
        if ( $version[0] == 1 && $version[1] <= 3 ) {
            $this->addColumn('attribute_code', array(
                'label' => Mage::helper('customer')->__('Customer Address Attribute'),
                'renderer' => $this->_getCustomerAddressRenderer(),
            ));
            $this->addColumn('output_name', array(
                'label' => Mage::helper('cataloginventory')->__('Output Name'),
                'style' => 'width:120px',
                'renderer' => $this->_getCustomerDisplay(),
            ));
        }
        parent::__construct();
    }

    /**
     * Retrieve customer attribute code column renderer
     *
     * @return Windsorcircle_Export_Block_Adminhtml_Form_Field_Customer_Options
     */
    protected function _getCustomerAddressRenderer()
    {
        if (!$this->_customerAddressRenderer) {
            $version = explode('.', Mage::getVersion());
            if ( $version[0] == 1 && $version[1] <= 3 ) {
                $layout = Mage::app()->getLayout();
            } else {
                $layout = $this->getLayout();
            }
            $this->_customerAddressRenderer = $layout->createBlock(
                'windsorcircle_export/adminhtml_form_field_customer_address_options', '',
                array('is_render_to_js_template' => true)
            );
            $this->_customerAddressRenderer->setClass('customer_address_group_select');
            $this->_customerAddressRenderer->setExtraParams('style="width:120px"');
        }
        return $this->_customerAddressRenderer;
    }

    /**
     * Retrieve display column renderer
     *
     * @return Windsorcircle_Export_Block_Adminhtml_Form_Field_Customer_Display
     */
    protected function _getCustomerDisplay()
    {
        if (!$this->_customerAddressDisplayRenderer) {
            $version = explode('.', Mage::getVersion());
            if ( $version[0] == 1 && $version[1] <= 3 ) {
                $layout = Mage::app()->getLayout();
            } else {
                $layout = $this->getLayout();
            }
            $this->_customerAddressDisplayRenderer = $layout->createBlock(
                'windsorcircle_export/adminhtml_form_field_display', ''
            );
        }
        return $this->_customerAddressDisplayRenderer;
    }

    /**
     * Prepare to render
     */
    protected function _prepareToRender()
    {
        $this->addColumn('attribute_code', array(
            'label' => Mage::helper('customer')->__('Customer Address Attribute'),
            'renderer' => $this->_getCustomerAddressRenderer(),
        ));
        $this->addColumn('output_name', array(
            'label' => Mage::helper('cataloginventory')->__('Output Name'),
            'style' => 'width:240px',
            'renderer' => $this->_getCustomerDisplay(),
        ));
        $this->_addAfter = false;
        $this->_addButtonLabel = Mage::helper('cataloginventory')->__('Add Custom Attribute');
    }

    /**
     * Prepare existing row data object
     *
     * @param Varien_Object
     */
    protected function _prepareArrayRow(Varien_Object $row)
    {
        $row->setData(
            'option_extra_attr_' . $this->_getCustomerAddressRenderer()->calcOptionHash($row->getData('attribute_code')),
            'selected="selected"'
        );
    }
}
