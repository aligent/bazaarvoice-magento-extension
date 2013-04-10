<?php
class Bazaarvoice_Block_Roi_Beacon extends Mage_Core_Block_Template
{
	private $_isEnabled;

	public function _construct()
	{
		// enabled/disabled in admin
		$this->_isEnabled = Mage::getStoreConfig("bazaarvoice/ROIBeacon/EnableROIBeacon") === "1";
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
				$orderDetails["orderId"] = $order->getId();
				$orderDetails["tax"] = number_format($order->getTaxAmount(), 2, ".", "");
				$orderDetails["shipping"] = number_format($order->getShippingAmount(), 2, ".", "");
				$orderDetails["total"] = number_format($order->getGrandTotal(), 2, ".", "");
				$orderDetails["currency"] = $order->getOrderCurrencyCode();
				$orderDetails["userId"] = $order->getCustomerId();
				$orderDetails["email"] = $order->getCustomerEmail();
				$orderDetails["nickname"] = $order->getCustomerEmail();
				$orderDetails["locale"] = Mage::getStoreConfig("general/locale/code", $order->getStoreId());

				$address = $order->getBillingAddress();
				$orderDetails["city"] = $address->getCity();
				$orderDetails["state"] = Mage::getModel("directory/region")->load($address->getRegionId())->getCode();
				$orderDetails["country"] = $address->getCountryId();
					
				$orderDetails["items"] = array();
				$items = $order->getAllVisibleItems();
				foreach ($items as $itemId => $item)
				{
					$product = Bazaarvoice_Helper_Data::getReviewableProductFromOrderItem($item);
					 
					$itemDetails = array();
					$itemDetails["sku"] = $product->getSku();
					$itemDetails["name"] = $item->getName();
					$itemDetails["price"] = number_format($item->getPrice(), 2, ".", "");
					$itemDetails["quantity"] = number_format($item->getQtyOrdered(), 0);

					$itemDetails["imageURL"] = $product->getImageUrl();
					
					array_push($orderDetails["items"], $itemDetails);
				}
			}
		}

		$orderDetailsJson = Mage::helper("core")->jsonEncode($orderDetails);
		return urldecode(stripslashes($orderDetailsJson));
	}
}