<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Mintpay\Mintpay\Model;



/**
 * Pay In Store payment method model
 */
class Mintpay extends \Magento\Payment\Model\Method\AbstractMethod
{

    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = 'mintpay';
  

}
