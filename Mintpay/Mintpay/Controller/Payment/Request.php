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

class Request extends \Magento\Framework\App\Action\Action
{

    protected $checkoutSession;
    protected $orderFactory;
    protected $resultFactory;
    protected $scopeConfig;
    protected $curl;
    protected $resultJsonFactory;
    protected $urlBuilder;

    /**
     * Constructor
     *
     * @param \Magento\Framework\App\Action\Context  $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Framework\Controller\ResultFactory $resultFactory,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\HTTP\Client\Curl $curl,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Framework\UrlInterface $urlBuilder
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->orderFactory = $orderFactory;
        $this->resultFactory = $resultFactory;
        $this->scopeConfig = $scopeConfig->getValue('payment/mintpay');
        $this->curl = $curl;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->urlBuilder = $urlBuilder;
        parent::__construct($context);
    }

    /**
     * Execute view action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        //Get current order detail from OrderFactory object.
        $orderId = $this->checkoutSession->getLastRealOrderId();

        if(empty($orderId)) {
            die("Aunthentication Error: Order is is empty.");
        }

        $order = $this->orderFactory->create()->loadByIncrementId($orderId);

        //Redirect to home page with error
        if(!isset($order)) {
            $this->_redirect('');
            return;
        }
        
        $order->setState(\Magento\Sales\Model\Order::STATE_CANCELED);
        $order->setStatus(\Magento\Sales\Model\Order::STATE_CANCELED);
        $order->save();

        
        //Get the selected product name from the OrderFactory object.

        foreach($order->getAllVisibleItems() as $item) {

            $orderItems[] = array(
                'name' => $item->getName(),
                'product_id' => $item->getId(),
                'sku' => $item->getSku(),
                'quantity' => $item->getQtyOrdered(),
                'unit_price' => $item->getPrice(),
                'created_date' => $item->getCreatedAt(),
                'updated_date'=> $item->getUpdatedAt(),
                'discount' => $item->getDiscountAmount()
            );
        }

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $customerSession = $objectManager->get('Magento\Customer\Model\Session');
                

        //Check whether customer is logged in or not into current merchant website.
        if($customerSession->isLoggedIn()) {
            
            $cust_email = $customerSession->getCustomer()->getEmail();
            
        } else {
            $billingAddress = $order->getBillingAddress();
            $cust_email = $billingAddress->getEmail();
        }
        
            
        $address_line = $order->getShippingAddress()->getStreet();
        $address = '';
        if (!empty($address_line)){
            foreach($address_line as $value){
                $address .= $value . ',';
            }
        }

        $merchantSecret = $this->scopeConfig['merchant_secret'];
        $merchantId = $this->scopeConfig['merchant_id'];
        $totalPrice = round($order->getGrandTotal(),2);
        $orderEntityId = $order->getEntityId();

        $successParams = [
            'orderId' => $orderId,
            'hash'    => base64_encode(hash_hmac('sha256',$merchantId . $totalPrice .  $orderEntityId, $merchantSecret))
        ];

        $successUrl = $this->urlBuilder->getUrl('mintpay/payment/response', ['_current' => true,'_use_rewrite' => true, '_query' => $successParams]);

        $failParams = [
            'orderId' => $orderId,
            'hash'    => base64_encode(hash_hmac('sha256', $orderEntityId, $merchantSecret))
        ];
        $failUrl = $this->urlBuilder->getUrl('mintpay/payment/response', ['_current' => true,'_use_rewrite' => true, '_query' => $failParams]);
        
        //Create basic form array.
        $order_data = array(
            'merchant_id'           => $merchantId,
            'order_id'              => $orderEntityId,
            'total_price'           => $totalPrice,
            'discount'              => $order->getDiscountAmount(),
            'customer_id'           => $order->getCustomerId(),
            'customer_email'        => $cust_email,
            'customer_telephone'    => $order->getShippingAddress()->getTelephone(),
            'ip'                    => $order->getRemoteIp(),
            'x_forwarded_for'       => $order->getXForwardedFor(),
            'delivery_street'       => $address,
            'delivery_region'       => $order->getShippingAddress()->getRegion(),
            'delivery_postcode'     => $order->getShippingAddress()->getPostcode(),
            'cart_created_date'     => $order->getCreatedAt(),
            'cart_updated_date'     => $order->getUpdatedAt(),
            'success_url'           => $successUrl,
            'fail_url'              => $failUrl,
            'products'              => $orderItems
        );

        $params = json_encode($order_data);

        if ($this->scopeConfig['sandbox_mode']) {
            $apiUrl = 'https://dev.mintpay.lk/user-order/api/';
            $redirectUrl = 'https://dev.mintpay.lk/user-order/login/';
        } else {        
            $apiUrl = 'https://app.mintpay.lk/user-order/api/';
            $redirectUrl = 'https://app.mintpay.lk/user-order/login/';
        }

        $this->curl->addHeader("Authorization","Token {$merchantSecret}");
        $this->curl->addHeader("Content-Type", "application/json");
        $this->curl->post($apiUrl, $params);

        $response = json_decode($this->curl->getBody(), TRUE);

        $comment = '';

        
        if(isset($response['message']) && $response['message']=='Success'){
            $strHtml = '<form name="mintpayform" action="'. $redirectUrl .'" method="post"/>';

            if (!empty($response['data'])) {
                $strHtml .= '<input type="hidden" name="purchase_id" value="' . htmlentities($response['data']) . '">';
            }

            $strHtml .= '</form>';
            $strHtml .= '<script type="text/javascript">';
            $strHtml .= 'document.mintpayform.submit()';
            $strHtml .= '</script>';                 
            
            echo $strHtml;
        }

        //else if ($order->getId() && $order->getState() != Order::STATE_CANCELED) {
        else if ($order->getId()) {
            $order->registerCancellation($comment)->save();
            $this->checkoutSession->restoreQuote();
            $this->_redirect('checkout/cart');
        }

        else{
            $this->_redirect('');
            return;
        }
        

        #return $this->resultJsonFactory->create()->setData($response);
            
    }
}
