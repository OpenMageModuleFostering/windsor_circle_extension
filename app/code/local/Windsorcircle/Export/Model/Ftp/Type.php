<?php
	class Windsorcircle_Export_Model_Ftp_Type{
		
		/**
		 * Select field in admin area for FTP Type
		 * @return array
		 */
		public function toOptionArray(){
			return array(
				array('value'=>1, 'label'=>Mage::helper('windsorcircle_export')->__('FTP')),
				array('value'=>2, 'label'=>Mage::helper('windsorcircle_export')->__('SFTP'))		
			);
		}
	}
