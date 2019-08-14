<?php
/**
 * Order Attribute Class used for select field in Admin
 *
 * @category  WindsorCircle
 * @package   Windsorcircle_Export
 * @author    Mark Hodge <mhodge@lyonscg.com>
 * @copyright Copyright (c) 2016 WindsorCircle (www.windsorcircle.com)
 */

class Windsorcircle_Export_Block_Adminhtml_Form_Field_Order_Fields
    extends Mage_Adminhtml_Block_System_Config_Form_Field_Array_Abstract
{
    /**
     * @var
     */
    protected $_renderer;

    /**
     * @var
     */
    protected $_displayRenderer;

    /**
     * Constructor
     */
    public function __construct()
    {
        $version = explode('.', Mage::getVersion());
        if ( $version[0] == 1 && $version[1] <= 3 ) {
            $this->addColumn('attribute_code', array(
                'label' => Mage::helper('windsorcircle_export')->__('Order Field'),
                'renderer' => $this->_getOrderRenderer(),
            ));
            $this->addColumn('output_name', array(
                'label' => Mage::helper('windsorcircle_export')->__('Output Name'),
                'style' => 'width:240px',
                'renderer' => $this->_getOrderDisplay(),
            ));
        }
        parent::__construct();
    }

    /**
     * Retrieve customer attribute code column renderer
     *
     * @return Windsorcircle_Export_Block_Adminhtml_Form_Field_Order_Options
     */
    protected function _getOrderRenderer()
    {
        if (!$this->_renderer) {
            $version = explode('.', Mage::getVersion());
            if ( $version[0] == 1 && $version[1] <= 3 ) {
                $layout = Mage::app()->getLayout();
            } else {
                $layout = $this->getLayout();
            }
            $this->_renderer = $layout->createBlock(
                'windsorcircle_export/adminhtml_form_field_order_options', '',
                array('is_render_to_js_template' => true)
            );
            $this->_renderer->setClass('order_options_value');
            $this->_renderer->setExtraParams('style="width:240px"');
        }
        return $this->_renderer;
    }

    /**
     * Retrieve display column renderer
     *
     * @return Windsorcircle_Export_Block_Adminhtml_Form_Field_Display
     */
    protected function _getOrderDisplay()
    {
        if (!$this->_displayRenderer) {
            $version = explode('.', Mage::getVersion());
            if ( $version[0] == 1 && $version[1] <= 3 ) {
                $layout = Mage::app()->getLayout();
            } else {
                $layout = $this->getLayout();
            }
            $this->_displayRenderer = $layout->createBlock(
                'windsorcircle_export/adminhtml_form_field_display', ''
            );
        }
        return $this->_displayRenderer;
    }

    /**
     * Prepare to render
     */
    protected function _prepareToRender()
    {
        $this->addColumn('attribute_code', array(
            'label' => Mage::helper('windsorcircle_export')->__('Order Field'),
            'style' => 'width:120px',
            'renderer' => $this->_getOrderRenderer(),
        ));
        $this->addColumn('output_name', array(
            'label' => Mage::helper('windsorcircle_export')->__('Output Name'),
            'style' => 'width:240px',
            'renderer' => $this->_getOrderDisplay(),
        ));
        $this->_addAfter = false;
        $this->_addButtonLabel = Mage::helper('windsorcircle_export')->__('Add Order Field');
    }
}
