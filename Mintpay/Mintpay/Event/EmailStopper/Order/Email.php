<?php
 
 namespace Mintpay\Mintpay\Event\EmailStopper\Order;
class Email implements \Magento\Framework\Event\ObserverInterface
{
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $this->objectManager = \Magento\Framework\App\ObjectManager::getInstance();
    try{
        $order = $observer->getEvent()->getOrder();
        $this->_current_order = $order;

        $payment = $order->getPayment()->getMethodInstance()->getCode();

        if($payment == 'Pay in 3 interest-free installments' || $payment == 'Pay in 3 interest-free installments'){
            $this->stopNewOrderEmail($order);
        }
    }
    catch (\ErrorException $ee){

    }
    catch (\Exception $ex)
    {

    }
    catch (\Error $error){

    }

}

public function stopNewOrderEmail(\Magento\Sales\Model\Order $order){
    $order->setCanSendNewEmailFlag(false);
    $order->setSendEmail(false);
    try{
        $order->save();
    }
    catch (\ErrorException $ee){

    }
    catch (\Exception $ex)
    {

    }
    catch (\Error $error){

    }
}
} 