<?php
namespace Mintpay\Mintpay\Controller\Payment;

class Response extends \Mintpay\Mintpay\Controller\AbstractCheckoutRedirectAction
{
	public function execute()
	{		
		
		//If payment getway response is empty then redirect to home page directory.		
		if(!isset($_GET['hash'])){
			$this->_redirect('');
			return;
		}

		
		$order_id 		 	= $_GET['orderId'];
		//Get the object of current order.
		$order = $this->getOrderDetailByOrderId($order_id); 

		//If order is empty then redirect to home page. Because order is not avaialbe.
		if(empty($order)) {
			$this->_redirect('');
			return;
		}


		$order_data = array(
            'merchant_id'           => $this->objConfigSettings['merchant_id'],
            'order_id'              => $order->getEntityId(),
            'total_price'           => round($order->getGrandTotal(),2)
        );

        $fail_data = array(
        	'order_id' => $order_id
        );

		if(base64_decode($_GET['hash']) == $this->getHashValidate($order_data)){
			//Set the complete status when payment is completed.
			$order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING);
			$order->setStatus(\Magento\Sales\Model\Order::STATE_PROCESSING);
			$order->save();				

			return $this->executeSuccessAction($order);

		}elseif(base64_decode($_GET['hash']) == $this->getHashValidate($fail_data)){

			$this->executeCancelAction();

		}else{
			
			$this->_redirect('');
			return;
		}
		
	}
}