<?php
class Windsorcircle_Export_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * Custom Attributes variable
     *
     * @var
     */
    protected $_customProductAttributes;
    protected $_customCustomerAttributes;
    protected $_customCustomerAddressAttributes;
    protected $_customOrderItemBuyRequestAttributes;
    protected $_customOrderAttributes;

    /**
     * Enterprise check variable
     *
     * @var
     */
    protected $_isEnterprise = null;

    /**
     * Get Extension Version
     *
     * @return string
     */
    public function getExtensionVersion()
    {
        return (string) Mage::getConfig()->getModuleConfig('Windsorcircle_Export')->version;
    }

    /**
     * Check if Enterprise version
     *
     * @return bool
     */
    public function isEnterprise()
    {
        if (is_null($this->_isEnterprise)) {
            if (method_exists('Mage', 'getEdition')) {
                $this->_isEnterprise = (bool)Mage::getEdition() === 'Enterprise';
            } else {
                if (Mage::getConfig()->getModuleConfig('Enterprise_Enterprise')
                    && Mage::getConfig()->getModuleConfig('Enterprise_AdminGws')
                    && Mage::getConfig()->getModuleConfig('Enterprise_Checkout')
                    && Mage::getConfig()->getModuleConfig('Enterprise_Customer')
                ) {
                    $this->_isEnterprise = true;
                } else {
                    $this->_isEnterprise = false;
                }
            }
        }
        return $this->_isEnterprise;
    }

    /**
     * Replace all tabs with spaces and replace all new lines with html <br />
     *
     * @param   $string
     * @param   int $tabspaces
     * @return  mixed
     */
    public function formatString($string, $tabspaces = 4) {
        $string = str_replace(array('\t', "\t"), str_repeat(" ",$tabspaces), $string);
        // use str_replace instead of nl2br because nl2br inserts html line breaks before all newlines but does not replace newlines
        $string = str_replace(array("\r\n", '\r\n', "\n\r", '\n\r', "\n", '\n', "\r", '\r'), '<br />', $string);
        return $string;
    }

    /**
     * Make value readable by Mage_Adminhtml_Block_System_Config_Form_Field_Array_Abstract
     *
     * @param mixed $value
     * @return array
     */
    public function makeArrayFieldValue($value, $prefix = null)
    {
        $value = $this->_unserializeValue($value);
        if (!$this->_isEncodedArrayFieldValue($value)) {
            $value = $this->_encodeArrayFieldValue($value, $prefix);
        }
        return $value;
    }

    /**
     * Make value ready for store
     *
     * @param mixed $value
     * @return string
     */
    public function makeStorableArrayFieldValue($value)
    {
        if ($this->_isEncodedArrayFieldValue($value)) {
            $value = $this->_decodeArrayFieldValue($value);
        }
        $value = $this->_serializeValue($value);
        return $value;
    }

    /**
     * Create a value from a storable representation
     *
     * @param mixed $value
     * @return array
     */
    protected function _unserializeValue($value)
    {
        if (is_string($value) && !empty($value)) {
            return unserialize($value);
        } else {
            return array();
        }
    }

    /**
     * Check whether value is in form retrieved by _encodeArrayFieldValue()
     *
     * @param mixed
     * @return bool
     */
    protected function _isEncodedArrayFieldValue($value)
    {
        if (!is_array($value)) {
            return false;
        }
        unset($value['__empty']);
        foreach ($value as $_id => $row) {
            if (!is_array($row) || !array_key_exists('attribute_code', $row)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Encode value to be used in Mage_Adminhtml_Block_System_Config_Form_Field_Array_Abstract
     *
     * @param array
     * @return array
     */
    protected function _encodeArrayFieldValue(array $value, $prefix = null)
    {
        $result = array();
        foreach ($value as $attributeCode) {
            $version = explode('.', Mage::getVersion());
            if ( $version[0] == 1 && $version[1] <= 3 ) {
                $_id = '_' . md5(uniqid(microtime().mt_rand(), true));
            } else {
                $_id = Mage::helper('core')->uniqHash('_');
            }
            $result[$_id] = array(
                'attribute_code' => $attributeCode,
                'output_name' => $prefix . $attributeCode,
            );
        }
        return $result;
    }

    /**
     * Decode value from used in Mage_Adminhtml_Block_System_Config_Form_Field_Array_Abstract
     *
     * @param array
     * @return array
     */
    protected function _decodeArrayFieldValue(array $value)
    {
        $result = array();
        unset($value['__empty']);
        foreach ($value as $_id => $row) {
            if (!is_array($row) || !array_key_exists('attribute_code', $row) || empty($row['attribute_code'])) {
                continue;
            }
            if (!in_array($row['attribute_code'], $result)) {
                $result[] = $row['attribute_code'];
            }
        }
        return $result;
    }

    /**
     * Generate a storable representation of a value
     *
     * @param mixed $value
     * @return string
     */
    protected function _serializeValue($value)
    {
        if (is_numeric($value)) {
            $data = (float)$value;
            return (string)$data;
        } else if (is_array($value)) {
            $data = array();
            foreach ($value as $attributeCode) {
                if (!array_key_exists($attributeCode, $data)) {
                    $data[] = $attributeCode;
                }
            }
            return serialize($data);
        } else {
            return '';
        }
    }

    /**
     * Get Custom Attributes from Admin Setting
     *
     * @return array|mixed
     */
    /**
     * Get Custom Attributes from Admin Setting
     *
     * @param string $type Should be 'product', 'customer', 'customer_address', 'order', or 'order_item_buy_request'
     *
     * @return array
     */
    public function getCustomAttributes($type)
    {
        /** If not of the correct type then just return */
        if (!in_array($type, array('product', 'customer', 'customer_address', 'order', 'order_item_buy_request'))) { return false; }

        $variableName = '_custom' . implode('', array_map("ucfirst", explode('_', $type))). 'Attributes';

        if (!$this->$variableName) {
            $customAttributes = Mage::helper('windsorcircle_export')
                ->makeArrayFieldValue(Mage::getStoreConfig('windsorcircle_export_options/messages/custom_' . $type . '_attributes'));


            $this->$variableName = array();
            foreach ($customAttributes as $attribute) {
                if (array_key_exists('attribute_code', $attribute)) {
                    array_push($this->$variableName, $attribute['attribute_code']);
                }
            }
        }
        return $this->$variableName;
    }

    /**
     * Get Current Memory Usage
     *
     * @return string
     */
    public function getMemoryUsage()
    {
        $mem_usage = memory_get_usage(true);

        if ($mem_usage < 1024) {
            return $mem_usage . " bytes";
        } elseif ($mem_usage < 1048576) {
            return round($mem_usage / 1024, 2) . " kilobytes";
        } else {
            return round($mem_usage / 1048576, 2) . " megabytes";
        }
    }
}
