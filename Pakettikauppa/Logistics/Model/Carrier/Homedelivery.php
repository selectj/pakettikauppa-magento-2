<?php
namespace Pakettikauppa\Logistics\Model\Carrier;

use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Rate\Result;
use Pakettikauppa\Logistics\Helper\Data;
use Pakettikauppa\Logistics\Helper\Api;
use Magento\Framework\App\Config\ScopeConfigInterface;

class Homedelivery extends \Magento\Shipping\Model\Carrier\AbstractCarrier implements
    \Magento\Shipping\Model\Carrier\CarrierInterface
{
    /**
     * @var string
     */
    protected $_code = 'pktkp_homedelivery';

    /**
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory
     * @param \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory
     * @param array $data
     */
    public function __construct(
        \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory,
        \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory,
        Api $apiHelper,
        Data $dataHelper,
        ScopeConfigInterface $scopeConfig,
        array $data = []
    ) {
        $this->dataHelper = $dataHelper;
        $this->apiHelper = $apiHelper;
        $this->_rateResultFactory = $rateResultFactory;
        $this->_rateMethodFactory = $rateMethodFactory;
        $this->scopeConfig = $scopeConfig;
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
    }

    /**
     * @return array
     */
    public function getAllowedMethods()
    {
        return ['pktkp_homedelivery' => $this->getConfigData('name')];
    }

    /**
     * @param RateRequest $request
     * @return bool|Result
     */
    public function collectRates(RateRequest $request)
    {
        if (!$this->getConfigFlag('active')) {
            return false;
        }
        /** @var \Magento\Shipping\Model\Rate\Result $result */
        $result = $this->_rateResultFactory->create();

        $homedelivery = $this->apiHelper->getHomeDelivery();
        if(count($homedelivery)>0){
          foreach ($homedelivery as $hd) {
            /** @var \Magento\Quote\Model\Quote\Address\RateResult\Method $method */
            $method = $this->_rateMethodFactory->create();
            $method->setCarrier('pktkp_homedelivery');
            $method->setCarrierTitle($hd->service_provider);
            $method->setMethod($hd->shipping_method_code);

            $db_price =  $this->scopeConfig->getValue('carriers/homedelivery/price');
            $db_title =  $this->scopeConfig->getValue('carriers/homedelivery/title');


            $conf_price = $this->getConfigData('price');
            $conf_title = $this->getConfigData('title');

            if($db_price == ''){
              $price = $conf_price;
            }else{
              $price = $db_price;
            }

            if($db_title == ''){
              $title = $conf_title;
            }else{
              $title = $db_title;
            }
            $method->setMethodTitle($title);
            $method->setPrice($price);
            $method->setCost($price);
            $result->append($method);
          }
        }
        return $result;

    }
}
