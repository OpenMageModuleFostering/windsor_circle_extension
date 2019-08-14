<?php
class WindsorCircle_Export_Model_State
{
    /**
     * Canceled State Variable
     *
     * @var array
     */
    protected $_canceledState;

    /**
     * Check state
     *
     * @param string $state
     * @return string 'Y' - Yes or 'N' - No
     */
    public function canceled($state){
        if (!$this->_canceledState) {
            $canceledState = Mage::getStoreConfig('windsorcircle_export_options/messages/canceled_state');
            $this->_canceledState = explode(',', $canceledState);

            if (is_array($this->_canceledState)) {
                $this->_canceledState = array_map('strtolower', $this->_canceledState);
            }
        }

        if (is_array($this->_canceledState)
            && in_array(strtolower($state), $this->_canceledState)
        ) {
            return 'Y';
        }
        return 'N';
    }
}
