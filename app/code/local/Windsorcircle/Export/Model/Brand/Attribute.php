<?php
/**
 * Brand Attribute
 *
 * @category   Lyons
 * @package    Windsorcircle_Export
 * @copyright  Copyright (c) 2014 Lyons Consulting Group (www.lyonscg.com)
 * @author     Mark Hodge (mhodge@lyonscg.com)
 */
class Windsorcircle_Export_Model_Brand_Attribute
{
    /**
     * Select field in admin area for Image Type
     * @return array
     */
    public function toOptionArray(){

        $version = explode('.', Mage::getVersion());
        if ( $version[0] == 1 && $version[1] <= 3 )
        {
            $attributeItems = Mage::getResourceModel('eav/entity_attribute_collection')
                                ->setEntityTypeFilter( Mage::getModel('eav/entity')->setType('catalog_product')->getTypeId() )
                                ->addVisibleFilter()
                                ->getItems();
        } else {
            $attributeItems = Mage::getResourceModel('catalog/product_attribute_collection')->getItems();
        }

        $attributes = array();
        $sortAttributes = array();
        foreach ($attributeItems as $attribute) {
            if ($attribute->getIsVisible()) {
                $sortAttributes[] = $attribute->getFrontendLabel();

                $attributes[] = array('value' => $attribute->getAttributeCode(),
                                      'label' => $attribute->getFrontendLabel());
            }
        }

        array_multisort($sortAttributes, SORT_ASC, $attributes);
        array_unshift($attributes, array('value' => 0, 'label' => '--' . Mage::helper('windsorcircle_export')->__('Please Select a value') . '--'));
        return $attributes;
    }
}
