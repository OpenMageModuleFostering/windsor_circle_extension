<?php
	class Windsorcircle_Export_Model_Date extends Mage_Core_Model_Abstract
	{
		protected function _construct(){
			$this->_init('windsorcircle_export/date');
		}

		/**
		 * Checks if the authDate is within one minute of the current time
		 * @param string $authDate
		 * @throws Exception
		 * @return bool true on success
		 */
		public function checkDate($authDate){
			date_default_timezone_set('UTC');
			$currentDate = strtotime(gmdate('c'));
			
			if(empty($authDate)){
				$authDate = strtotime('+1 week');
			} else {
				$authDate = strtotime($authDate);
			}
			
			$diff = $currentDate - $authDate;
			
			if(abs($diff) <= 600)
				return true;
			else
				throw new Exception('authDate is not correct');
		}
	}
