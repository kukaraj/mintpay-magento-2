<?php

namespace Mintpay\Mintpay\Controller;

use Magento\Framework\App\Action\Context;
use Magento\Checkout\Model\Session;
use Magento\Sales\Model\OrderFactory;
use Magento\Framework\Controller\ResultFactory;
use Magento\Catalog\Model\Session as catalogSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Customer\Model\Session as Customer;
use Mintpay\Mintpay\Controller\AbstractCheckoutAction;
use Mintpay\Mintpay\Helper\Checkout;
use Mintpay\Mintpay\Helper\MintpayRequest;
use Mintpay\Mintpay\Helper\MintpayHash;

abstract class AbstractCheckoutRedirectAction extends AbstractCheckoutAction
{
    protected $objCheckoutHelper, $objCustomer;
    protected $objMintpayRequestHelper, $objMintpayMetaHelper;
    protected $objMintpayHashHelper, $objConfigSettings;
    protected $objCatalogSession;
    protected $curl;
    protected $resultJsonFactory;

    public function __construct(
        Context $context,
        Session $checkoutSession, OrderFactory $orderFactory,
        Customer $customer, Checkout $checkoutHelper,
        MintpayRequest $mintpayRequest,
        MintpayHash $mintpayHash, ScopeConfigInterface $configSettings ,
        catalogSession $catalogSession,
        \Magento\Framework\HTTP\Client\Curl $curl,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
    ) {

        parent::__construct($context, $checkoutSession, $orderFactory);
        $this->objCheckoutHelper = $checkoutHelper;
        $this->objCustomer = $customer;
        $this->objMintpayRequestHelper = $mintpayRequest;
        $this->objMintpayHashHelper = $mintpayHash;
        $this->objConfigSettings = $configSettings->getValue('payment/mintpay');
        $this->objCatalogSession = $catalogSession; 
        $this->curl = $curl;
        $this->resultJsonFactory = $resultJsonFactory;       
    }

    //This object is hold the custom filed data for payment method like selected store Card's, other setting, etc.
    protected function getCatalogSession() {
        return $this->objCatalogSession;
    }

    //Get the Magento configuration setting object that hold global setting for Merchant configuration
    protected function getConfigSettings() {
        return $this->objConfigSettings;
    }

    //Get the Mintpay plugin Hash helper class object to check hash value is valid or not. Also generate the hash for any request.
    protected function getHashHelper() {
        return $this->objMintpayHashHelper;
    }

    //Get the Meta helper object. It is responsible for storing the data into database. like mintpay_meta, mintpay_token table.
    protected function getMetaDataHelper() {
        return $this->objMintpayMetaHelper;
    }

    //Get the mintpay request helper class. It is responsible for construct the current user request for mintpay Payment Gateway.
    protected function getMintpayRequest($paramter,$isloggedIn) {
        return $this->objMintpayRequestHelper->mintpay_construct_request($paramter,$isloggedIn);
    }

    //This is magento object to get the customer object.
    protected function getCustomerSession() {
        return $this->objCustomer;
    }

    //Get the mintpay cehckout object. It is reponsible for hold the current users cart detail's
    protected function getCheckoutHelper() {
        return $this->objCheckoutHelper;
    }

    //This function is used to redirect to customer message action method after make successfully payment.
    protected function executeSuccessAction($order){
        $this->getCheckoutSession()->setLastSuccessQuoteId($order->getQuoteId());
        $this->getCheckoutSession()->setLastQuoteId($order->getQuoteId());
        $this->getCheckoutSession()->setLastOrderId($order->getEntityId()); // Required, otherwise getOrderId() is empty on success.phtml
        $this->getCheckoutSession()->setLastRealOrderId($order->getIncrementId());

        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath('checkout/onepage/success',  array('_secure'=>true));
        return $resultRedirect;
    }
    
    //This function is redirect to cart after customer is cancel the payment.
    protected function executeCancelAction(){
        $this->getCheckoutHelper()->cancelCurrentOrder('');
        $this->getCheckoutHelper()->restoreQuote();
        $this->redirectToCheckoutCart();
    }

    // Get Magento Curl object.
    protected function getCurlRequest($apikey,$order_data,$orderId) {
        $url = $this->objMintpayRequestHelper->getPaymentGetwayApiUrl();
        $success_url = $this->objMintpayRequestHelper->getMerchantReturnUrl($order_data,$orderId);
        $order_data["success_url"]  = $success_url;
        $order_data["fail_url"] = $this->objMintpayRequestHelper->getMerchantFailReturnUrl($orderId);
        $params = json_encode($order_data);
        $this->curl->addHeader("Authorization","Token {$apikey}");
        $this->curl->addHeader("Content-Type", "application/json");
        $this->curl->post($url, $params);

        $response = $this->curl->getBody();
        return $response;
    }  

    protected function getResults($response){
        return $this->resultJsonFactory->create()->setData($response);
    }

    protected function getHashValidate($order_data) {
        return $this->objMintpayHashHelper->createRequestHashValue($order_data,$this->objConfigSettings['merchant_secret']);

    }  
}