<?php
	require_once(Mage::getBaseDir('lib') . DS . 'Varien' . DS . 'Io' . DS . 'Sftp.php');

	class Windsorcircle_Export_Model_Sftp extends Varien_Io_Sftp
	{
		protected function _construct(){
			$this->_init('windsorcircle_export/sftp');
		}
		
		/**
	     * Write a file
	     * Overrides default class because $mode by default is set to NET_SFTP_STRING
	     * @param $src Must be a local file name
	     */
	    public function write($filename, $src, $mode = NET_SFTP_LOCAL_FILE)
	    {
	        return $this->_connection->put($filename, $src, $mode);
	    }
	}
