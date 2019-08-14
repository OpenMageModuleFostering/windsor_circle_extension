<?php
/**
 * Source file for Canceled Order State
 *
 * @category  Lyons
 * @package   Windsorcircle_Export
 * @author    Mark Hodge <mhodge@lyonscg.com>
 * @copyright Copyright (c) 2015 Lyons Consulting Group (www.lyonscg.com)
 */

class Windsorcircle_Export_Model_Source_Canceled_State
{
    /**
     * Option Array for Canceled Order State
     *
     * @return array
     */
    public function toOptionArray()
    {
        $arr = array();
        if ((Mage::helper('windsorcircle_export')->isEnterprise()
                && version_compare(Mage::getVersion(), '1.10.0.0', '<'))
            || version_compare(Mage::getVersion(), '1.5.0.0', '<')
        ) {
            $visible = Mage::getModel('sales/order_config')->getVisibleOnFrontStates();
            $invisible = Mage::getModel('sales/order_config')->getInvisibleOnFrontStates();
            $orderStates = array_merge(
                array_combine($invisible, $invisible),
                array_combine($visible, $visible)
            );
        } else {
            $orderStates = Mage::getModel('sales/order_config')->getStates();
        }
        foreach ($orderStates as $value => $label) {
            $arr[] = array('value' => $value, 'label' => $label);
        }
        return $arr;
    }
}
