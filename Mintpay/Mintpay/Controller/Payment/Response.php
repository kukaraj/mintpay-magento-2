<?php
/**
 * Mintpay Payment Gateway
 * Copyright (C) 2019 
 * 
 * This file included in Mintpay/Mintpay is licensed under OSL 3.0
 * 
 * http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * Please see LICENSE.txt for the full text of the OSL 3.0 license
 */

namespace Mintpay\Mintpay\Controller\Payment;

class Response extends \Magento\Framework\App\Action\Action
{

    protected $orderFactory;
    protected $scopeConfig;
    protected $urlBuilder;
    protected $checkoutSession;
    protected $orderSender;

    /**
     * Constructor
     *
     * @param \Magento\Framework\App\Action\Context  $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender
    ) {
        $this->orderFactory = $orderFactory;
        $this->scopeConfig = $scopeConfig->getValue('payment/mintpay');
        $this->urlBuilder = $urlBuilder;
        $this->checkoutSession = $checkoutSession;
        $this->orderSender = $orderSender;
        parent::__construct($context);
    }

    /**
     * Execute view action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
       //If payment getway response is empty then redirect to home page directory.      
        if(!isset($_GET['hash'])){
            $this->_redirect('');
            return;
        }

        
        $orderId           = $_GET['orderId'];
        //Get the object of current order.
        $order = $this->orderFactory->create()->loadByIncrementId($orderId); 

        //If order is empty then redirect to home page. Because order is not avaialbe.
        if(empty($order)) {
            $this->_redirect('');
            return;
        }

        $merchantSecret = $this->scopeConfig['merchant_secret'];
        $merchantId = $this->scopeConfig['merchant_id'];
        $totalPrice = round($order->getGrandTotal(),2);
        $orderEntityId = $order->getEntityId();



        if(base64_decode($_GET['hash']) == hash_hmac('sha256',$merchantId . $totalPrice .  $orderEntityId, $merchantSecret)){
            //Set the complete status when payment is completed.
            $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING);
            $order->setStatus(\Magento\Sales\Model\Order::STATE_PROCESSING);
            $order->save();             

            $this->checkoutSession->setLastSuccessQuoteId($order->getQuoteId());
            $this->checkoutSession->setLastQuoteId($order->getQuoteId());
            $this->checkoutSession->setLastOrderId($order->getEntityId()); // Required, otherwise getOrderId() is empty on success.phtml
            $this->checkoutSession->setLastRealOrderId($order->getIncrementId());

            $order->setCanSendNewEmailFlag(true);
            $order->save();
            $this->checkoutSession->setForceOrderMailSentOnSuccess(true);
            $this->orderSender->send($order, true);

            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setPath('checkout/onepage/success',  array('_secure'=>true));
            return $resultRedirect;

        }else if(base64_decode($_GET['hash']) == hash_hmac('sha256', $orderEntityId, $merchantSecret)){
            $comment = '';
            $order->registerCancellation($comment)->save();
            $this->checkoutSession->restoreQuote();
            $this->_redirect('checkout/cart');

        }else{
            
            $this->_redirect('');
            return;
        }
        
    }
}
