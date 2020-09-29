<?php

namespace Mintpay\Mintpay\Helper;

use \Magento\Framework\App\Helper\AbstractHelper;

class MintpayHash extends AbstractHelper
{
	private $params, $hashValue;

    // This function is used to check the hash value is valid or not .
	public function isValidHashValue($parameter,$secret_key) {

		if (array_key_exists('merchant_id', $parameter)) {
			$this->params .= $parameter['merchant_id'];
		}

		if (array_key_exists('order_id', $parameter)) {
			$this->params .= $parameter['order_id'];
		}


		if (array_key_exists('total_price', $parameter)) {
			$this->params .= $parameter['total_price'];
		}
		

		//Generate hash based on hash alogorithm.
		$hash = hash_hmac('sha256', $this->params, $secret_key); 

        //Return hash value result.
		if (strcasecmp($hash, $parameter['hash_value']) == 0) {
			return true;
		}

		return false;
	}

    // This function is used to generate the hash value for the current Merchant user request.
	public function createRequestHashValue($parameter,$secretKey){

		if(array_key_exists('merchant_id',$parameter)) {
			if(!empty($parameter['merchant_id'])) 
				$this->hashValue .= $parameter['merchant_id'];
		}

		if(array_key_exists('order_id',$parameter)) {
			if(!empty($parameter['order_id'])) 
				$this->hashValue .= $parameter['order_id'];
		}


		if(array_key_exists('total_price',$parameter)) {
			if(!empty($parameter['total_price'])) 
				$this->hashValue .= $parameter['total_price'];
		}


  		//Return hash value result.
		return hash_hmac('sha256', $this->hashValue, $secretKey);
	}
}