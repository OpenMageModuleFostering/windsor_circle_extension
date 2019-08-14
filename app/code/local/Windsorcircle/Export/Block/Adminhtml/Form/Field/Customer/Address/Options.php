<?php
/**
 * Customer Address Options Class
 *
 * @category  Lyons
 * @package   Windsorcircle_Export
 * @author    Mark Hodge <mhodge@lyonscg.com>
 * @copyright Copyright (c) 2014 Lyons Consulting Group (www.lyonscg.com)
 */ 

class Windsorcircle_Export_Block_Adminhtml_Form_Field_Customer_Address_Options extends Mage_Core_Block_Html_Select
{
    /**
     * Customer groups cache
     *
     * @var array
     */
    private $_customerAddressAttributes;

    /**
     * Retrieve allowed customer groups
     *
     * @param int $groupId  return name by customer group id
     * @return array|string
     */
    protected function _getCustomerAddressAttributes()
    {
        if (is_null($this->_customerAddressAttributes)) {
            $this->_customerAddressAttributes = array();

            $type = Mage::getModel('eav/entity_type')->loadByCode('customer_address');
            $collection = Mage::getResourceModel('eav/entity_attribute_collection')->setEntityTypeFilter($type);

            $this->_customerAddressAttributes[0] = '';
            foreach ($collection as $item) {
                $label = $item->getFrontendLabel();
                if (!empty($label)) {
                    /* @var $item Mage_Catalog_Model_Resource_Eav_Attribute */
                    $this->_customerAddressAttributes[$item->getAttributeCode()] = $label;
                }
            }
        }
        return $this->_customerAddressAttributes;
    }

    public function setInputName($value)
    {
        return $this->setName($value);
    }

    /**
     * Render block HTML
     *
     * @return string
     */
    public function _toHtml()
    {
        if (!$this->getOptions()) {
            $coreHelper = Mage::helper('core');
            foreach ($this->_getCustomerAddressAttributes() as $attributeCode => $label) {
                if (version_compare(Mage::getVersion(), '1.4.0.0', '<')) {
                    $this->addOption($attributeCode, $this->quoteEscape($this->htmlEscape($label)));
                } else {
                    if (method_exists($coreHelper, 'quoteEscape')) {
                        $this->addOption($attributeCode, $coreHelper->quoteEscape($this->escapeHtml($label)));
                    } else {
                        $this->addOption($attributeCode, $this->quoteEscape($this->escapeHtml($label)));
                    }
                }
            }
        }
        return parent::_toHtml();
    }

    /**
     * Used for when Magento quoteEscape function is not available
     *
     * Escape quotes inside html attributes
     * Use $addSlashes = false for escaping js that inside html attribute (onClick, onSubmit etc)
     *
     * @param string $data
     * @param bool $addSlashes
     * @return string
     */
    public function quoteEscape($data, $addSlashes = false)
    {
        if ($addSlashes === true) {
            $data = addslashes($data);
        }
        return htmlspecialchars($data, ENT_QUOTES, null, false);
    }
}
