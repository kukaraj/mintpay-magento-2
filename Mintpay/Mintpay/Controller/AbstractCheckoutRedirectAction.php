<?php

namespace Mintpay\Mintpay\Controller;

use Magento\Framework\App\Action\Context;
use Magento\Checkout\Model\Session;
use Magento\Sales\Model\OrderFactory;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Mintpay\Mintpay\Controller\AbstractCheckoutAction;
use Mintpay\Mintpay\Helper\Checkout;
use Mintpay\Mintpay\Helper\MintpayRequest;
use Mintpay\Mintpay\Helper\MintpayHash;

abstract class AbstractCheckoutRedirectAction extends AbstractCheckoutAction
{
    protected $objCheckoutHelper;
    protected $objMintpayRequestHelper;
    protected $objMintpayHashHelper, $objConfigSettings;
    protected $curl;
    protected $resultJsonFactory;
    protected $checkoutSession;

    public function __construct(
        Context $context,
        Session $checkoutSession, 
        OrderFactory $orderFactory,
        ScopeConfigInterface $configSettings,
        Checkout $checkoutHelper,
        MintpayRequest $mintpayRequest,
        MintpayHash $mintpayHash, 
        \Magento\Framework\HTTP\Client\Curl $curl,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
    ) {

        parent::__construct($context, $checkoutSession, $orderFactory);
        $this->objCheckoutHelper = $checkoutHelper;
        $this->objMintpayRequestHelper = $mintpayRequest;
        $this->objMintpayHashHelper = $mintpayHash;
        $this->objConfigSettings = $configSettings->getValue('payment/mintpay');
        $this->curl = $curl;
        $this->resultJsonFactory = $resultJsonFactory;       
    }

    
    //Get the Magento configuration setting object that hold global setting for Merchant configuration
    protected function getConfigSettings() {
        return $this->objConfigSettings;
    }

    //Get the Mintpay plugin Hash helper class object to check hash value is valid or not. Also generate the hash for any request.
    protected function getHashHelper() {
        return $this->objMintpayHashHelper;
    }

    
    //Get the mintpay request helper class. It is responsible for construct the current user request for mintpay Payment Gateway.
    protected function getMintpayRequest($paramter,$isloggedIn) {
        return $this->objMintpayRequestHelper->mintpay_construct_request($paramter,$isloggedIn);
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
    protected function getCurlRequest() {
        return $this->curl;
        
    }  

    protected function getResults($response){
        return $this->resultJsonFactory->create()->setData($response);
    }

    protected function getHashValidate($order_data) {
        return $this->objMintpayHashHelper->createRequestHashValue($order_data,$this->objConfigSettings['merchant_secret']);

    }  
}