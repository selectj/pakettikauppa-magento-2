<?php

namespace Pakettikauppa\Logistics\Plugin\Carrier;

use Magento\Quote\Model\Cart\ShippingMethodConverter;
use Magento\Quote\Api\Data\ShippingMethodInterface;
use Magento\Quote\Api\Data\ShippingMethodExtensionFactory;
use Magento\Framework\Registry;


class Image
{
    /**
     * @var ShippingMethodExtensionFactory
     */
    protected $extensionFactory;

    /**
     * @var Registry
     */
    protected $registry;

    /**
     * DeliveryDate constructor.
     *
     * @param ShippingMethodExtensionFactory $extensionFactory
     * @param Registry $registry
     */
    public function __construct(
      ShippingMethodExtensionFactory $extensionFactory,
      Registry $registry
    )
    {
      $this->extensionFactory = $extensionFactory;
      $this->registry = $registry;
    }

    /**
     * Add delivery date information to the carrier data object
     *
     * @param ShippingMethodConverter $subject
     * @param ShippingMethodInterface $result
     * @return ShippingMethodInterface
     */
    public function afterModelToDataObject(ShippingMethodConverter $subject, ShippingMethodInterface $result)
    {

        // MATCH METHOD AND IMAGE
        $images = $this->registry->registry('pktkpicons');
        $method = $result->getMethodCode();

        // CREATE EXTENSION ATTRIBUTE
        if ($result->getExtensionAttributes()){
          $extensibleAttribute = $result->getExtensionAttributes();
        } else{
          $extensibleAttribute = $this->extensionFactory->create();
        }

        $extensibleAttribute->setPostidistance($images[$method]['distance'] ?? '');
        $extensibleAttribute->setPostidescription($images[$method]['description'] ?? '');
        // ATTACHE EXTENSION ATTIBUTE {image}
        $result->setExtensionAttributes($extensibleAttribute);
        return $result;
    }
}
