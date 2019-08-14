<?php
	class Windsorcircle_Export_Model_Ftp extends Mage_Core_Model_Abstract
	{
		
		protected function _construct(){
			$this->_init('windsorcircle_export/ftp');
		}

        /**
         * Create a blank .flg file
         *
         * @param   $file
         * @return  array    Array[0] - Base File Name, Array[1] - Absolute File Location
         * @throws  Exception
         */
        protected function createFlgFile($file){
			$file = Mage::getBaseDir('tmp') . DS . basename($file, '.txt') . '.flg';
			$handle = fopen($file, 'w');

			if($handle == false){
				throw new Exception('Cannot create flg file for '. basename($file, '.txt'));
			}

			fclose($handle);
			return array(basename($file), $file);
		}

        /**
         * Send files via FTP Type (Ftp or SFtp)
         *
         * @param array $files
         * @return bool
         * @throws Exception
         */
        public function sendFiles(array $files){

			try {
				// Value of 1 is FTP, value of 2 is SFTP
				if(Mage::getStoreConfig('windsorcircle_export_options/messages/ftp_type') == '1'){
					require_once(Mage::getBaseDir('lib') . DS . 'Varien' . DS . 'Io' . DS . 'Ftp.php');
					$ftp = new Varien_Io_Ftp();
					$ftp->open(array('host' 	=>	Mage::getStoreConfig('windsorcircle_export_options/messages/ftp_host'), 
									 'user'		=>	Mage::getStoreConfig('windsorcircle_export_options/messages/ftp_user'),
									 'password'	=>	Mage::getStoreConfig('windsorcircle_export_options/messages/ftp_password'),
									 'path'		=>	Mage::getStoreConfig('windsorcircle_export_options/messages/ftp_folder'),
									 'passive'	=>	true
									 ));
					$prefix = '';
				} elseif (Mage::getStoreConfig('windsorcircle_export_options/messages/ftp_type') == '2'){
					$ftp = Mage::getModel('windsorcircle_export/sftp');
					$ftp->open(array('host'		=>	Mage::getStoreConfig('windsorcircle_export_options/messages/ftp_host'),
									 'username'	=>	Mage::getStoreConfig('windsorcircle_export_options/messages/ftp_user'),
									 'password'	=>	Mage::getStoreConfig('windsorcircle_export_options/messages/ftp_password')
									 ));
					$prefix = '/' . Mage::getStoreConfig('windsorcircle_export_options/messages/ftp_folder') . '/';
				}
			} catch (Exception $e){
				throw new Exception('FTP Exception: '.$e);
			}

			foreach($files as $key => $file) {
				// Varien_Io_Ftp suppresses error messages so only way to check if successfull is if not null
				if($ftp->write($prefix . basename($files[$key]), $file) != null){
					$flgFile = $this->createFlgFile($file);
					$ftp->write($prefix . $flgFile[0], $flgFile[1]);
				} else {
					$ftp->close();
					throw new Exception('Error writing file to server');
				}
			}
			
			$ftp->close();
			
			return true;
		}

		/**
		 * Test connection to ftp server
		 *
		 * @throws Exception
		 */
		public function testConnection()
		{
			try {
				// Value of 1 is FTP, value of 2 is SFTP
				if(Mage::getStoreConfig('windsorcircle_export_options/messages/ftp_type') == '1'){
					require_once(Mage::getBaseDir('lib') . DS . 'Varien' . DS . 'Io' . DS . 'Ftp.php');
					$ftp = new Varien_Io_Ftp();
					$ftp->open(array('host' 	=>	Mage::getStoreConfig('windsorcircle_export_options/messages/ftp_host'),
						'user'		=>	Mage::getStoreConfig('windsorcircle_export_options/messages/ftp_user'),
						'password'	=>	Mage::getStoreConfig('windsorcircle_export_options/messages/ftp_password'),
						'path'		=>	Mage::getStoreConfig('windsorcircle_export_options/messages/ftp_folder'),
						'passive'	=>	true
					));
				} elseif (Mage::getStoreConfig('windsorcircle_export_options/messages/ftp_type') == '2'){
					$ftp = Mage::getModel('windsorcircle_export/sftp');
					$ftp->open(array('host'		=>	Mage::getStoreConfig('windsorcircle_export_options/messages/ftp_host'),
						'username'	=>	Mage::getStoreConfig('windsorcircle_export_options/messages/ftp_user'),
						'password'	=>	Mage::getStoreConfig('windsorcircle_export_options/messages/ftp_password')
					));
				}
			} catch (Exception $e){
				return 'Error connecting - ' . $e;
			}
			return 'Connection Successful!';
		}
	}
