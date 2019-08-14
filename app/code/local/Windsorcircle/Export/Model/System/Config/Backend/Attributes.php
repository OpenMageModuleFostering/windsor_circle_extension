<?php
/**
 * Backend Class to save value in admin
 *
 * @category  Lyons
 * @package   Windsorcircle_Export
 * @author    Mark Hodge <mhodge@lyonscg.com>
 * @copyright Copyright (c) 2014 Lyons Consulting Group (www.lyonscg.com)
 */ 

class Windsorcircle_Export_Model_System_Config_Backend_Attributes extends Mage_Core_Model_Config_Data
{
    /**
     * Process data after load
     */
    protected function _afterLoad()
    {
        $value = $this->getValue();

        $prefix = '';
        switch($this->getPath()) {
            case 'windsorcircle_export_options/messages/custom_product_attributes':
                $prefix = 'custom_product_';
                break;
            case 'windsorcircle_export_options/messages/custom_customer_attributes':
                $prefix = 'custom_customer_';
                break;
            case 'windsorcircle_export_options/messages/custom_customer_address_attributes':
                $prefix = 'custom_customer_address_';
                break;
            case 'windsorcircle_export_options/messages/custom_order_item_buy_request_attributes':
                $prefix = 'custom_order_item_buy_request_';
                break;
            case 'windsorcircle_export_options/messages/custom_order_attributes':
                $prefix = 'custom_order_';
                break;
        }

        $value = Mage::helper('windsorcircle_export')->makeArrayFieldValue($value, $prefix);
        $this->setValue($value);
    }

    /**
     * Prepare data before save
     */
    protected function _beforeSave()
    {
        $value = $this->getValue();
        $value = Mage::helper('windsorcircle_export')->makeStorableArrayFieldValue($value);
        $this->setValue($value);
    }
}
