<?php
class Bazaarvoice_Connector_Block_Roi_Beacon extends Mage_Core_Block_Template
{
    private $_isEnabled;

    public function _construct()
    {
        // enabled/disabled in admin
        $this->_isEnabled = Mage::getStoreConfig('bazaarvoice/general/enable_roibeacon') === '1'
                                && Mage::getStoreConfig('bazaarvoice/general/enable_bv') === '1';
    }

    /**
     * returns true if feature is enabled in admin, otherwise returns false
     * @return bool
     */
    public function getIsEnabled()
    {
        return $this->_isEnabled;
    }

    /**
     * returns serialized order details data for transmission to Bazaarvoice
     * @return string
     */
    public function getOrderDetails()
    {
        $orderDetails = array();
        $orderId = Mage::getSingleton('checkout/session')->getLastOrderId();
        if ($orderId)
        {
            $order = Mage::getModel('sales/order')->load($orderId);
            if ($order->getId())
            {
                $address = $order->getBillingAddress();

                $orderDetails['orderId'] = $order->getIncrementId();
                $orderDetails['tax'] = number_format($order->getTaxAmount(), 2, '.', '');
                $orderDetails['shipping'] = number_format($order->getShippingAmount(), 2, '.', '');
                $orderDetails['total'] = number_format($order->getGrandTotal(), 2, '.', '');
                $orderDetails['city'] = $address->getCity();
                $orderDetails['state'] = Mage::getModel('directory/region')->load($address->getRegionId())->getCode();
                $orderDetails['country'] = $address->getCountryId();
                $orderDetails['currency'] = $order->getOrderCurrencyCode();

                $orderDetails['items'] = array();
                // if families are enabled, get all items
                if(Mage::getStoreConfig('bazaarvoice/feeds/families')){
                    $items = $order->getAllItems();
                } else {
                    $items = $order->getAllVisibleItems();
                }
                foreach ($items as $itemId => $item)
                {
                    $product = Mage::helper('bazaarvoice')->getReviewableProductFromOrderItem($item);
                    $product = Mage::getModel('catalog/product')->load($product->getId());
                    // skip configurable items if families are enabled
                    if(Mage::getStoreConfig('bazaarvoice/feeds/families') && $product->getTypeId() == Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE) continue;
                     
                    $itemDetails = array();
                    $itemDetails['sku'] = Mage::helper('bazaarvoice')->getProductId($product);
                    $itemDetails['name'] = $item->getName();
                    // 'category' is not included.  Mage products can be in 0 - many categories.  Should we try to include it?
                    $itemDetails['price'] = number_format($item->getPrice(), 2, '.', '');
                    $itemDetails['quantity'] = number_format($item->getQtyOrdered(), 0);
                    $itemDetails['imageUrl'] = $product->getImageUrl();
                    
                    if(Mage::getStoreConfig('bazaarvoice/feeds/families') && $item->getParentItem()) {
                        if(strpos($itemDetails['imageUrl'], "placeholder/image.jpg")) {
                            // if product families are enabled and product has no image, use configurable image
                            $parentId = $item->getParentItem()->getProductId();
                            $parent = Mage::getModel('catalog/product')->load($parentId);
                            $itemDetails['imageUrl'] = $parent->getImageUrl();
                        }
                        // also get price from parent item
                        $itemDetails['price'] = number_format($item->getParentItem()->getPrice(), 2, '.', '');
                    }                    
                    
                    array_push($orderDetails['items'], $itemDetails);
                }

                $orderDetails['userId'] = $order->getCustomerId();
                $orderDetails['email'] = $order->getCustomerEmail();
                $orderDetails['nickname'] = $order->getCustomerEmail();
                // There is no 'deliveryDate' yet
                $orderDetails['locale'] = Mage::getStoreConfig('bazaarvoice/general/locale', $order->getStoreId());

                // Add partnerSource field
                $orderDetails['partnerSource'] = 'Magento Extension r' . Mage::helper('bazaarvoice')->getExtensionVersion();
            }
        }
        Mage::log($orderDetails);
        $orderDetailsJson = Mage::helper('core')->jsonEncode($orderDetails);
        return urldecode(stripslashes($orderDetailsJson));
    }
}
