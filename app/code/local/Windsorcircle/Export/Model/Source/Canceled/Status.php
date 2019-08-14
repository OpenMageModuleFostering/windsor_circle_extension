<?php
/**
 * Source file for Canceled Order Status
 *
 * @category  Lyons
 * @package   Windsorcircle_Export
 * @author    Mark Hodge <mhodge@lyonscg.com>
 * @copyright Copyright (c) 2015 Lyons Consulting Group (www.lyonscg.com)
 */

class Windsorcircle_Export_Model_Source_Canceled_Status
{
    /**
     * Option Array for Canceled Order Status
     *
     * @return array
     */
    public function toOptionArray()
    {
        $orderStatuses = Mage::getSingleton('sales/order_status')->getCollection();
        $arr = array();
        foreach ($orderStatuses as $orderStatus) {
            $arr[] = array('value' => $orderStatus->getStatus(),
                'label' => Mage::helper('windsorcircle_export')->__($orderStatus->getLabel()));
        }
        return $arr;
    }
}
