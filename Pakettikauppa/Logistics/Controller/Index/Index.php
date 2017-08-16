<?php


namespace Pakettikauppa\Logistics\Controller\Index;

use Magento\Framework\App\Action\Context;

use Magento\Checkout\Model\Cart;

class Index extends \Magento\Framework\App\Action\Action
{

    public function __construct(
        Cart $cart,
        Context $context
    ) {
        $this->cart = $cart;
        parent::__construct($context);
    }
    /**
     * Index action
     *
     * @return $this
     */
    public function execute()
    {
        if ($_POST) {
          $quote = $this->cart->getQuote();
          $zipcode = $_POST['zip_code'];

          $quote->setData('pickuppoint_zip',$zipcode);

          $shipping_address = $quote->getShippingAddress();

          $shipping_address->setCollectShippingRates(true)
                          ->collectShippingRates();

          $quote->save();
          echo "Success";
        }
    }
}
