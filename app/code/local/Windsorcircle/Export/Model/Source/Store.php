<?php
/**
 * Source file for Store/Website selection in admin
 *
 * @category  Lyons
 * @package   Windsorcircle_Export
 * @author    Mark Hodge <mhodge@lyonscg.com>
 * @copyright Copyright (c) 2016 Lyons Consulting Group (www.lyonscg.com)
 */

class Windsorcircle_Export_Model_Source_Store
{
    /**
     * Get admin website/store values for admin selection
     *
     * @return array
     */
    public function toOptionArray()
    {
        return Mage::getSingleton('adminhtml/system_store')->getStoreValuesForForm(false, true);
    }
}