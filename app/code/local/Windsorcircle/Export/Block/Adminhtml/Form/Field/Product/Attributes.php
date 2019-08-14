<?php
/**
 * Produt Attributes Class
 *
 * @category  Lyons
 * @package   Windsorcircle_Export
 * @author    Mark Hodge <mhodge@lyonscg.com>
 * @copyright Copyright (c) 2014 Lyons Consulting Group (www.lyonscg.com)
 */ 

class Windsorcircle_Export_Block_Adminhtml_Form_Field_Product_Attributes
    extends Mage_Adminhtml_Block_System_Config_Form_Field_Array_Abstract
{
    /**
     * @var
     */
    protected $_productRenderer;

    /**
     * @var
     */
    protected $_productDisplayRenderer;

    /**
     * Constructor
     */
    public function __construct()
    {
        $version = explode('.', Mage::getVersion());
        if ( $version[0] == 1 && $version[1] <= 3 ) {
            $this->addColumn('attribute_code', array(
                'label' => Mage::helper('customer')->__('Product Attribute'),
                'renderer' => $this->_getProductRenderer(),
            ));
            $this->addColumn('output_name', array(
                'label' => Mage::helper('cataloginventory')->__('Output Name'),
                'style' => 'width:120px',
                'renderer' => $this->_getProductDisplay(),
            ));
        }
        parent::__construct();
    }

    /**
     * Retrieve product attribute code column renderer
     *
     * @return Windsorcircle_Export_Block_Adminhtml_Form_Field_Product_Options
     */
    protected function _getProductRenderer()
    {
        if (!$this->_productRenderer) {
            $version = explode('.', Mage::getVersion());
            if ( $version[0] == 1 && $version[1] <= 3 ) {
                $layout = Mage::app()->getLayout();
            } else {
                $layout = $this->getLayout();
            }
            $this->_productRenderer = $layout->createBlock(
                'windsorcircle_export/adminhtml_form_field_product_options', '',
                array('is_render_to_js_template' => true)
            );
            $this->_productRenderer->setClass('product_group_select');
            $this->_productRenderer->setExtraParams('style="width:120px"');
        }
        return $this->_productRenderer;
    }

    /**
     * Retrieve display column renderer
     *
     * @return Windsorcircle_Export_Block_Adminhtml_Form_Field_Product_Display
     */
    protected function _getProductDisplay()
    {
        if (!$this->_productDisplayRenderer) {
            $version = explode('.', Mage::getVersion());
            if ( $version[0] == 1 && $version[1] <= 3 ) {
                $layout = Mage::app()->getLayout();
            } else {
                $layout = $this->getLayout();
            }
            $this->_productDisplayRenderer = $layout->createBlock(
                'windsorcircle_export/adminhtml_form_field_display', ''
            );
        }
        return $this->_productDisplayRenderer;
    }

    /**
     * Prepare to render
     */
    protected function _prepareToRender()
    {
        $this->addColumn('attribute_code', array(
            'label' => Mage::helper('customer')->__('Product Attribute'),
            'renderer' => $this->_getProductRenderer(),
        ));
        $this->addColumn('output_name', array(
            'label' => Mage::helper('cataloginventory')->__('Output Name'),
            'style' => 'width:240px',
            'renderer' => $this->_getProductDisplay(),
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
            'option_extra_attr_' . $this->_getProductRenderer()->calcOptionHash($row->getData('attribute_code')),
            'selected="selected"'
        );
    }
}
