<?php

namespace Mintpay\Mintpay\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Mintpay\Mintpay\Helper\MintpayHash;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\UrlInterface;

class MintpayRequest extends AbstractHelper{

	private $objConfigSettings;
	private $objMintpayHashHelper;
	private $objStoreManagerInterface;

	function __construct(ScopeConfigInterface $configSettings, MintpayHash $mintpayHash, 
				StoreManagerInterface $storeManagerInterface, 
			    UrlInterface $urlBuilder) {

		$this->objConfigSettings = $configSettings->getValue('payment/mintpay');
		$this->objMintpayHashHelper = $mintpayHash;
		$this->objStoreManagerInterface = $storeManagerInterface;
		$this->urlBuilder = $urlBuilder;
	}


	//This function is used to genereate the request for make payment to payment getaway.
	public function mintpay_construct_request($data,$isLoggedIn) {
		
		$strHtml = '<form name="mintpayform" action="'. $this->getPaymentGetwayRedirectUrl() .'" method="post"/>';

		if (!empty($data)) {
			$strHtml .= '<input type="hidden" name="purchase_id" value="' . htmlentities($data) . '">';
		}


		$strHtml .= '</form>';
		$strHtml .= '<script type="text/javascript">';
		$strHtml .= 'document.mintpayform.submit()';
		$strHtml .= '</script>';			
		return $strHtml;
	}

    //Get Payment Getway redirect url to redirect Test URL or Live URL to Mintpay PG. It is depending upon the Merchant selected settings in configurations.
    function getPaymentGetwayRedirectUrl() {

    	if ($this->objConfigSettings['sandbox_mode']) {
    		return 'https://dev.mintpay.lk/user-order/login/';
    	} else {  		
    		return 'https://app.mintpay.lk/user-order/login/';
    	}
    }


    function getPaymentGetwayApiUrl() {

    	if ($this->objConfigSettings['sandbox_mode']) {
    		return 'https://dev.mintpay.lk/user-order/api/';
    	} else {  		
    		return 'https://app.mintpay.lk/user-order/api/';
    	}
    }

    //Get the merchant website return URL.
    function getMerchantReturnUrl($order_data,$orderId) {

    	#$baseUrl = $this->objStoreManagerInterface->getStore()->getBaseUrl();
    	$hash = $this->objMintpayHashHelper->createRequestHashValue($order_data,$this->objConfigSettings['merchant_secret']);
    	$queryParams = [
    		'orderId' => $orderId,
    		'hash'    => base64_encode($hash)
    	];
    	$url = $this->urlBuilder->getUrl('mintpay/payment/response', ['_current' => true,'_use_rewrite' => true, '_query' => $queryParams]);
    	return  $url;
    }
	
	function getMerchantSuccessReturnUrl() {

    	$baseUrl = $this->objStoreManagerInterface->getStore()->getBaseUrl();
    	return  $baseUrl.'checkout/onepage/success/';
    }

    function getMerchantFailReturnUrl($orderId) {
    	
    	#$baseUrl = $this->objStoreManagerInterface->getStore()->getBaseUrl();
    	$data = array(
    		'order_id' => $orderId
    	);
    	$hash = $this->objMintpayHashHelper->createRequestHashValue($data,$this->objConfigSettings['merchant_secret']);
    	$queryParams = [
    		'orderId' => $orderId,
    		'hash'    => base64_encode($hash)
    	];
    	$url = $this->urlBuilder->getUrl('mintpay/payment/response', ['_current' => true,'_use_rewrite' => true, '_query' => $queryParams]);
    	return  $url;
    }
	
	
}