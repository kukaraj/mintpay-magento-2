<?php

namespace Mintpay\Mintpay\Controller\Payment;

class Request extends \Mintpay\Mintpay\Controller\AbstractCheckoutRedirectAction
{	
	public function execute() {

		//Get current order detail from OrderFactory object.
		$orderId = $this->getCheckoutSession()->getLastRealOrderId();

		if(empty($orderId)) {
			die("Aunthentication Error: Order is is empty.");
		}

		$order = $this->getOrderDetailByOrderId($orderId);

		//Redirect to home page with error
		if(!isset($order)) {
			$this->_redirect('');
			return;
		}
		
		$order->setState(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);
		$order->setStatus(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);
		$order->save();

		
		$customerSession = $this->getCustomerSession();
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
		
		//Create basic form array.
		$order_data = array(
            'merchant_id'           => $this->objConfigSettings['merchant_id'],
            'order_id'              => $order->getEntityId(),
            'total_price'           => round($order->getGrandTotal(),2),
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
            'products'              => $orderItems,
        );

        
    
        $merchant_secret = $this->objConfigSettings['merchant_secret'];

        $response = json_decode($this->getCurlRequest($merchant_secret,$order_data,$orderId), TRUE);

        if ($response === null) {
        	$this->executeCancelAction();
        }
        else{
        	if(isset($response['message']) && $response['message']=='Success'){

        		#return $this->getResults($order_data);        
        		echo $this->getMintpayRequest($response['data'],$customerSession->isLoggedIn());
        	}

        	else{
        		$this->executeCancelAction();
        	}
        }

        #return $this->getResults($response);
        	
	}
}