<?php
class WindsorCircle_Export_Model_Openssl
{
	// Unencoded base64 variable
	private $unencoded = '';

	// Hash of API key
	private $hash = '';

	// Verified key of $this->unencoded
	private $verified = '';

	// urlDecoded authDate
	private $authDate = '';

	/**
	 * Checks if the decrypted authToken is equal to the hash digest of the API key
	 *
	 * @param $authToken
	 * @param $authDate
	 * @return bool
	 * @throws Exception
	 */
	public function valid($authToken, $authDate){

		if(empty($authToken)){
			throw new Exception('authToken is empty.');
		}

		// URL decode authDate
		$this->authDate = urldecode($authDate);

		// Base 64 encoded authToken
		$this->unencoded = base64_decode($authToken);

		// Computes a digest based off of API key
		$this->hash = hash('sha1', Mage::getStoreConfig('windsorcircle_export_options/messages/api_key') . $this->authDate, true);

		// Decrypts $this->unencoded with the public key
		openssl_public_decrypt($this->unencoded, $this->verified, Mage::getStoreConfig('windsorcircle_export_options/messages/public_key'));

		// compares authToken and hash
		$equal = strcmp($this->verified, $this->hash);

		// If equal returns 0, otherwise < 0 or > 0
		if($equal == 0)
			return true;
		else{
			throw new Exception('authToken and computed hash do not match.');
		}
	}

	/**
	 * OpenSSL Decryption of message using iv value
	 *
	 * @param $message
	 * @param $iv
	 * @return string
	 */
	public function decrypt($message, $iv)
	{
		$encryptionMethod = 'AES-128-CBC';
		$apiKey = Mage::getStoreConfig('windsorcircle_export_options/messages/api_key');

		return openssl_decrypt($message, $encryptionMethod, $apiKey, false, $iv);
	}
}
