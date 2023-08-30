<?php

namespace Pakettikauppa\Logistics\Helper;

// require_once(__DIR__ . '/pakettikauppa/autoload.php');
require_once __DIR__ . '/pakettikauppa/Shipment.php';
require_once __DIR__ . '/pakettikauppa/Shipment/Sender.php';
require_once __DIR__ . '/pakettikauppa/Shipment/Receiver.php';
require_once __DIR__ . '/pakettikauppa/Shipment/AdditionalService.php';
require_once __DIR__ . '/pakettikauppa/Shipment/Info.php';
require_once __DIR__ . '/pakettikauppa/Shipment/Parcel.php';
require_once __DIR__ . '/pakettikauppa/Client.php';
require_once __DIR__ . '/pakettikauppa/SimpleXMLElement.php';

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Pakettikauppa\Client;
use Pakettikauppa\Shipment;
use Pakettikauppa\Shipment\AdditionalService;
use Pakettikauppa\Shipment\Info;
use Pakettikauppa\Shipment\Parcel;
use Pakettikauppa\Shipment\Receiver;
use Pakettikauppa\Shipment\Sender;
use Psr\Log\LoggerInterface;

class Api extends \Magento\Framework\App\Helper\AbstractHelper
{
    protected $client;
    protected $key;
    protected $secret;
    protected $development;
    protected $active;
    protected $pickup_methods;
    private $logger;

    /**
     * Api constructor.
     *
     * @param LoggerInterface      $logger
     * @param DirectoryList        $directory_list
     * @param ScopeConfigInterface $scopeConfig
     *
     * @throws \Exception
     */
    public function __construct(
        LoggerInterface $logger,
        DirectoryList $directory_list,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->pickup_methods = [
            ['id' => 'posti', 'name' => 'Posti'],
            ['id' => 'matkahuolto', 'name' => 'Matkahuolto'],
            ['id' => 'dbschenker', 'name' => 'DB Schenker']
        ];
        $this->logger = $logger;
        $this->directory_list = $directory_list;
        $this->scopeConfig = $scopeConfig;
        $this->active = $this->scopeConfig->getValue('pakettikauppa_config/store/active', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        if ($this->active == 1) {
            $this->development = true;
        } else {
            $this->development = false;
        }
        if ($this->development) {
            $this->client = new Client(['test_mode' => true]);
        } else {
            $this->key = $this->scopeConfig->getValue('pakettikauppa_config/api/api_key', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
            $this->secret = $this->scopeConfig->getValue('pakettikauppa_config/api/api_secret_key', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
            if (isset($this->key) && isset($this->secret)) {
                $params['api_key'] = $this->key;
                $params['secret'] = $this->secret;
                $this->client = new Client($params);
            } else {
                throw new \Exception('Please insert API and secret key.');
            }
        }
    }

    public function getPickuppoints($query)
    {
        $allowed = [];
        foreach ($this->pickup_methods as $method) {
            if ($this->scopeConfig->getValue('carriers/' . $method['id'] . '_pickuppoint/active', \Magento\Store\Model\ScopeInterface::SCOPE_STORE) == 1) {
                $allowed[] = $method['name'];
            }
        }

        $result = $this->client->searchPickupPointsByText($query, implode(', ', $allowed), 10);
        return json_decode($result);
    }

    public function getLabel($trackingCode) {
        return $this->client->fetchShippingLabels([$trackingCode]);
    }
    public function getHomeDelivery($all = false)
    {
        $client = $this->client;
        $result = [];
        $methods = json_decode($client->listShippingMethods());

        if (empty($methods)) {
            return $result;
        }

        if ($all === true) {
            return $methods;
        }

        foreach ($methods as $method) {
            if ($method->name != null && $method->home_delivery == true) {
                $result[] = $method;
            }
        }
        return $result;
    }

    public function createShipment($order)
    {
        $sender = new Sender();
        $store = $order->getStoreId();

        $_sender_name = $this->scopeConfig->getValue('pakettikauppa_config/store/name', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $_sender_address = $this->scopeConfig->getValue('pakettikauppa_config/store/address', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $_sender_city = $this->scopeConfig->getValue('pakettikauppa_config/store/city', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $_sender_postcode = $this->scopeConfig->getValue('pakettikauppa_config/store/postcode', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $_sender_country = $this->scopeConfig->getValue('pakettikauppa_config/store/country', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $sender->setName1($_sender_name);
        $sender->setAddr1($_sender_address);
        $sender->setPostcode($_sender_postcode);
        $sender->setCity($_sender_city);
        $sender->setCountry($_sender_country);

        $shipping_data = $order->getShippingAddress();

        $firstname = $shipping_data->getData('firstname');
        $middlename = $shipping_data->getData('middlename');
        $lastname = $shipping_data->getData('lastname');
        $name = $firstname . ' ' . $middlename . ' ' . $lastname;

        $company = $shipping_data->getData('company');

        $receiver = new Receiver();
        // if company exists, then use company name as name1 field
        if (!empty($company)) {
            $receiver->setName1($company);
            $receiver->setName2($name);
        } else {
            $receiver->setName1($name);
        }

        $receiver->setAddr1($shipping_data->getData('street'));
        $receiver->setPostcode($shipping_data->getData('postcode'));
        $receiver->setCity($shipping_data->getData('city'));
        $receiver->setCountry($shipping_data->getData('country_id'));
        $receiver->setEmail($shipping_data->getData('email'));
        $receiver->setPhone($shipping_data->getData('telephone'));

        $info = new Info();
        $info->setReference($order->getIncrementId());

        $parcel = new Parcel();
        $parcel->setReference($order->getIncrementId());
        $parcel->setWeight($order->getData('weight')); // kg
        // GET VOLUME
        $parcel->setVolume(0.001); // m3

        $shipment = new Shipment();
        $shipment->setShippingMethod($order->getData('paketikauppa_smc')); // shipping_method_code that you can get by using listShippingMethods()
        $shipment->setSender($sender);
        $shipment->setReceiver($receiver);
        $shipment->setShipmentInfo($info);
        $shipment->addParcel($parcel);

        if (strpos($order->getShippingMethod(), 'pickuppoint') !== false) {
            $additional_service = new AdditionalService();
            $additional_service->setServiceCode(2106);
            $additional_service->addSpecifier('pickup_point_id', $order->getData('pickup_point_id'));
            $shipment->addAdditionalService($additional_service);
        }

        $client = $this->client;
        try {
            if ($client->createTrackingCode($shipment)) {
                if ($client->fetchShippingLabel($shipment)) {
                    return (string)$shipment->getTrackingCode();
                }
            }
        } catch (\Exception $ex) {
            $this->logger->critical('Shipment not created, please double check your store settings on STORE view level. Additional message: ' . $ex->getMessage());
            throw new \Magento\Framework\Exception\LocalizedException(
                __($ex->getMessage())
            );
        }
    }

    public function getTracking($code)
    {
        $client = $this->client;
        $tracking = $client->getShipmentStatus($code);
        return json_decode($tracking);
    }
}
