<?php
namespace Pakettikauppa\Logistics\Model\Carrier;

use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Rate\Result;
use Pakettikauppa\Logistics\Helper\Data;
use Pakettikauppa\Logistics\Helper\Api;
use Magento\Sales\Model\Order\Shipment\Track;

class Pickuppoint extends \Magento\Shipping\Model\Carrier\AbstractCarrier implements
    \Magento\Shipping\Model\Carrier\CarrierInterface
{
    /**
     * @var string
     */
    protected $_code = 'pktkp_pickuppoint';

    /**
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory
     * @param \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory,
        \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        Track $trackFactory,
        Api $apiHelper,
        Data $dataHelper,
        array $data = []
    ) {
        $this->dataHelper = $dataHelper;
        $this->apiHelper = $apiHelper;
        $this->_rateResultFactory = $rateResultFactory;
        $this->_rateMethodFactory = $rateMethodFactory;
        $this->_storeManager=$storeManager;
        $this->trackFactory = $trackFactory;
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
    }

    /**
     * @return array
     */
    public function getAllowedMethods()
    {
        return ['pktkp_pickuppoint' => $this->getConfigData('name')];
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


        $zip = $this->dataHelper->getZip();
        if ($zip) {
        $pickuppoints = $this->apiHelper->getPickuppoints($zip);

        foreach ($pickuppoints as $pp) {
          /** @var \Magento\Quote\Model\Quote\Address\RateResult\Method $method */
          $method = $this->_rateMethodFactory->create();

          $method->setCarrier('pktkp_pickuppoint');
          $method->setCarrierTitle($pp->provider." - ".$pp->name.": ".$pp->street_address.", ".$pp->postcode.", ".$pp->city);

          $method->setMethod($pp->pickup_point_id);
          $method->setMethodTitle('Pickuppoint');

          $amount = $this->getConfigData('price');

          $method->setPrice($amount);
          $method->setCost($amount);

          $result->append($method);
        }
        return $result;
      }
    }

    public function isTrackingAvailable()
    {
        return true;
    }
    public function getTrackingInfo($tracking)
    {
      $title = 'Pakettikauppa';
      $base_url = $this->_storeManager->getStore()->getBaseUrl().'logistics/tracking/';
      $track = $this->trackFactory;
      $track->setUrl($base_url.'?code='.$tracking)
            ->setCarrierTitle($title)
            ->setTracking($tracking);
      return $track;
    }
}
