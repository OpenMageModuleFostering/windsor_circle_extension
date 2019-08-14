<?php
/**
 * Form Field Options for Buy Request Product Options
 *
 * @category  Lyons
 * @package   Windsorcircle_Export
 * @author    Mark Hodge <mhodge@lyonscg.com>
 * @copyright Copyright (c) 2015 Lyons Consulting Group (www.lyonscg.com)
 */

class Windsorcircle_Export_Block_Adminhtml_Form_Field_Order_Item_Buy_Request
    extends Mage_Adminhtml_Block_System_Config_Form_Field_Array_Abstract
{
    /**
     * @var
     */
    protected $_orderItemRenderer;

    /**
     * @var
     */
    protected $_orderItemDisplayRenderer;

    /**
     * Constructor
     */
    public function __construct()
    {
        $version = explode('.', Mage::getVersion());
        if ( $version[0] == 1 && $version[1] <= 3 ) {
            $this->addColumn('attribute_code', array(
                'label' => Mage::helper('sales')->__('Order Item Product Option'),
                'renderer' => $this->_getOrderItemRenderer(),
            ));
            $this->addColumn('output_name', array(
                'label' => Mage::helper('sales')->__('Output Name'),
                'style' => 'width:240px',
                'renderer' => $this->_getOrderItemDisplay(),
            ));
        }
        parent::__construct();
    }

    /**
     * Retrieve customer attribute code column renderer
     *
     * @return Windsorcircle_Export_Block_Adminhtml_Form_Field_Order_Item_Buy_Options
     */
    protected function _getOrderItemRenderer()
    {
        if (!$this->_orderItemRenderer) {
            $version = explode('.', Mage::getVersion());
            if ( $version[0] == 1 && $version[1] <= 3 ) {
                $layout = Mage::app()->getLayout();
            } else {
                $layout = $this->getLayout();
            }
            $this->_orderItemRenderer = $layout->createBlock(
                'windsorcircle_export/adminhtml_form_field_order_item_buy_options', '',
                array('is_render_to_js_template' => true)
            );
            $this->_orderItemRenderer->setClass('order_item_product_options_value');
            $this->_orderItemRenderer->setExtraParams('style="width:240px"');
        }
        return $this->_orderItemRenderer;
    }

    /**
     * Retrieve display column renderer
     *
     * @return Windsorcircle_Export_Block_Adminhtml_Form_Field_Display
     */
    protected function _getOrderItemDisplay()
    {
        if (!$this->_orderItemDisplayRenderer) {
            $version = explode('.', Mage::getVersion());
            if ( $version[0] == 1 && $version[1] <= 3 ) {
                $layout = Mage::app()->getLayout();
            } else {
                $layout = $this->getLayout();
            }
            $this->_orderItemDisplayRenderer = $layout->createBlock(
                'windsorcircle_export/adminhtml_form_field_display', ''
            );
        }
        return $this->_orderItemDisplayRenderer;
    }

    /**
     * Prepare to render
     */
    protected function _prepareToRender()
    {
        $this->addColumn('attribute_code', array(
            'label' => Mage::helper('sales')->__('Order Item Product Option'),
            'style' => 'width:120px',
            'renderer' => $this->_getOrderItemRenderer(),
        ));
        $this->addColumn('output_name', array(
            'label' => Mage::helper('sales')->__('Output Name'),
            'style' => 'width:240px',
            'renderer' => $this->_getOrderItemDisplay(),
        ));
        $this->_addAfter = false;
        $this->_addButtonLabel = Mage::helper('sales')->__('Add Buy Request Option');
    }
}