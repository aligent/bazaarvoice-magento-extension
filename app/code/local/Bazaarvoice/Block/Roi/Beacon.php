<?php
class Bazaarvoice_Block_Roi_Beacon extends Mage_Core_Block_Template
{
	private $_isEnabled;
	
	public function _construct()
	{
		$this->_isEnabled = true;		
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
            	// Mage::log($order->debug(), null, "order.log", true);
            	$orderDetails["orderId"] = $order->getId();
            	$orderDetails["tax"] = number_format($order->getTaxAmount(), 2, ".", "");
            	$orderDetails["shipping"] = number_format($order->getShippingAmount(), 2, ".", "");
            	$orderDetails["total"] = number_format($order->getGrandTotal(), 2, ".", "");
            	$orderDetails["currency"] = $order->getOrderCurrencyCode();
            	$orderDetails["userId"] = $order->getCustomerId();
            	$orderDetails["email"] = $order->getCustomerEmail();
            	$orderDetails["nickname"] = $order->getCustomerEmail();
            	
            	$address = $order->getShippingAddress();
            	$orderDetails["city"] = $address->getCity();
            	$orderDetails["state"] = $address->getRegion();
            	$orderDetails["country"] = $address->getCountryId();
            	
            	$orderDetails["items"] = array();
            	$items = $order->getAllItems();
            	foreach ($items as $itemId => $item)
            	{
            		$itemDetails = array();
            		$itemDetails["sku"] = $item->getSku();
            		$itemDetails["name"] = $item->getName();
            		$itemDetails["price"] = number_format($item->getPrice(), 2, ".", "");
            		$itemDetails["quantity"] = number_format($item->getQtyOrdered(), 0);
            		
            		$product = Mage::getModel("catalog/product")->load($item->getProductId());
            		$itemDetails["imageURL"] = $product->getImageUrl();
            		
            		array_push($orderDetails["items"], $itemDetails);
            	}            	
            }
        }
        
        $orderDetailsJson = Mage::helper("core")->jsonEncode($orderDetails);
        return urldecode(stripslashes(Zend_Json::prettyPrint($orderDetailsJson, array("indent" => "  "))));
	}
}