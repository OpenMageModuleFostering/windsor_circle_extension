<?php
class WindsorCircle_Export_Model_Status
{
    /**
     * Canceled Status Variable
     *
     * @var array
     */
    protected $_canceledStatus;

    /**
     * Check status
     *
     * @param string $status
     * @return string 'Y' - Yes or 'N' - No
     */
    public function canceled($status){
        if (!$this->_canceledStatus) {
            $canceledStatus = Mage::getStoreConfig('windsorcircle_export_options/messages/canceled_status');
            $this->_canceledStatus = explode(',', $canceledStatus);

            if (is_array($this->_canceledStatus)) {
                $this->_canceledStatus = array_map('strtolower', $this->_canceledStatus);
            }
        }

        if (is_array($this->_canceledStatus)
            && in_array(strtolower($status), $this->_canceledStatus)
        ) {
            return 'Y';
        }
        return 'N';
    }
}
