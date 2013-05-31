<?php
class Bazaarvoice_Model_ExportPurchaseFeed extends Mage_Core_Model_Abstract {

    const ALREADY_SENT_IN_FEED_FLAG = "sent_in_bv_postpurchase_feed";
    const TRIGGER_EVENT_PURCHASE = "purchase";
    const TRIGGER_EVENT_SHIP = "ship";

    const DEBUG_OUTPUT = false;

    protected function _construct() {}

    public function exportPurchaseFeed() {
        Mage::log("Start Bazaarvoice purchase feed generation");

        // Short-circuit if the purchase feed export is not enabled
        if(Mage::getStoreConfig("bazaarvoice/PurchaseFeed/EnablePurchaseFeed") !== "1") {
            Mage::log("    BV - purchase feed generation is disabled ");
            Mage::log("End Bazaarvoice purchase feed generation");
            return;
        }


        $purchaseFeedFilePath = Mage::getBaseDir("var") . DS . 'export' . DS . 'bvfeeds';
        $purchaseFeedFileName = 'purchaseFeed-' . date('U') . '.xml';

        // Make sure that the directory we want to write to exists.
        $ioObject = new Varien_Io_File();
        try {
            $ioObject->open(array('path'=>$purchaseFeedFilePath));
        } catch (Exception $e) {
            $ioObject->mkdir($purchaseFeedFilePath, 0777, true);
            $ioObject->open(array('path'=>$purchaseFeedFilePath));
        }


        if($ioObject->streamOpen($purchaseFeedFileName)) {

            $ioObject->streamWrite("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<Feed xmlns=\"http://www.bazaarvoice.com/xs/PRR/PostPurchaseFeed/4.9\">\n");


            Mage::log("    BV - processing all orders");
            $numOrdersExported = $this->processOrders($ioObject);
            Mage::log("    BV - completed processing all orders");

            $ioObject->streamWrite("</Feed>\n");
            $ioObject->streamClose();


            // Don't bother uploading if there are no orders in the feed
            $upload = false;
            if ($numOrdersExported > 0) {
                $destinationFile = "/" . Mage::getStoreConfig("bazaarvoice/PurchaseFeed/ExportPath") . "/" . Mage::getStoreConfig("bazaarvoice/PurchaseFeed/ExportFileName");
                $sourceFile = $purchaseFeedFilePath . DS . $purchaseFeedFileName;

                $upload = Mage::helper('Bazaarvoice')->uploadFile($sourceFile, $destinationFile);
            }


            if (!$upload) {
                Mage::log("    Bazaarvoice FTP upload failed! [filename = " . $purchaseFeedFileName . "]");
            } else {
                Mage::log("    Bazaarvoice FTP upload success! [filename = " . $purchaseFeedFileName . "]");
                $ioObject->rm($purchaseFeedFileName);
            }
            
        }

        Mage::log("End Bazaarvoice purchase feed generation");
    }

    private function processOrders($ioObject) {

        // Gather settings for how this feed should be generated
        $triggeringEvent = Mage::getStoreConfig("bazaarvoice/PurchaseFeed/TriggeringEvent") === Bazaarvoice_Model_Source_TriggeringEvent::SHIPPING? self::TRIGGER_EVENT_SHIP : self::TRIGGER_EVENT_PURCHASE;
        $numDaysLookback = Mage::getStoreConfig("bazaarvoice/PurchaseFeed/NumDaysLookback");
        $delayDaysSinceEvent = Mage::getStoreConfig("bazaarvoice/PurchaseFeed/DelayDaysSinceEvent");
        Mage::log("    BV - Config {TriggeringEvent: " . $triggeringEvent
                                . ", NumDaysLookback: " . $numDaysLookback
                                . ", NumDaysLookbackStartDate: " . $this->getNumDaysLookbackStartDate($numDaysLookback)
                                . ", DelayDaysSinceEvent: " . $delayDaysSinceEvent
                                . ", DelayDaysThreshold: " . date("c", $this->getDelayDaysThresholdTimestamp($delayDaysSinceEvent)) . "}");

        // Initialize references to the object model accessors
        $productModel = Mage::getModel("catalog/product"); //Getting product model for access to product related functions
        $orderModel = Mage::getModel("sales/order");

        // Get a collection of all the orders
        $orders = $orderModel->getCollection();

        // Filter the returned orders to minimize processing as much as possible.  More available operations in method _getConditionSql in Varien_Data_Collection_Db.
        // Status is "complete" or "closed"
        $orders->addFieldToFilter("status", array("in" => array("complete", "closed")));
        // Only orders created within our lookback window
        $orders->addFieldToFilter("created_at", array("gteq" => $this->getNumDaysLookbackStartDate($numDaysLookback)));
        // Exclude orders that have been previously sent in a feed
        $orders->addFieldToFilter(self::ALREADY_SENT_IN_FEED_FLAG, array("null" => "null"));  // adds an "IS NULL" filter to the BV flag column


        $numOrdersExported = 0; // Keep track of how many orders we include in the feed

        foreach($orders->getAllIds() as $orderId) {

            $order = $orderModel->load($orderId);
            $store = $order->getStore();

            if (!$this->shouldIncludeOrder($order, $triggeringEvent, $delayDaysSinceEvent)) {
                continue;
            }



            $numOrdersExported++;

            $ioObject->streamWrite("<Interaction>\n");
            $ioObject->streamWrite("    <EmailAddress>" . $order->getCustomerEmail() . "</EmailAddress>\n");
            $ioObject->streamWrite("    <Locale>" . $store->getConfig("general/locale/code") . "</Locale>\n");
            $ioObject->streamWrite("    <UserName>" . $order->getCustomerName() . "</UserName>\n");
            $ioObject->streamWrite("    <UserID>" . $order->getCustomerId() . "</UserID>\n");
            $ioObject->streamWrite("    <TransactionDate>" . $this->getTriggeringEventDate($order, $triggeringEvent) . "</TransactionDate>\n");
            $ioObject->streamWrite("    <Products>\n");
            foreach($order->getAllVisibleItems() as $item) {
            	$product = Mage::helper('Bazaarvoice')->getReviewableProductFromOrderItem($item);
                if (!is_null($product)) {
                    $ioObject->streamWrite("        <Product>\n");
                    $ioObject->streamWrite("            <ExternalId>" . Mage::helper('Bazaarvoice')->getProductId($product) . "</ExternalId>\n");
                    $ioObject->streamWrite("            <Name>" . htmlspecialchars($product->getName(), ENT_QUOTES, "UTF-8") . "</Name>\n");
                    $ioObject->streamWrite("            <ImageUrl>" . $product->getImageUrl() . "</ImageUrl>\n");
                    $ioObject->streamWrite("            <Price>" . number_format((float)$item->getOriginalPrice(), 2) . "</Price>\n");
                    $ioObject->streamWrite("        </Product>\n");
                }
            }
            $ioObject->streamWrite("    </Products>\n");
            $ioObject->streamWrite("</Interaction>\n");

            $order->setData(self::ALREADY_SENT_IN_FEED_FLAG, 1);
            $order->save();
            $order->reset();  //Forces a reload of various collections that the object caches internally so that the next time we load from the orderModel, we'll get a completely new object.

        }

        return $numOrdersExported;
    }

    private function orderToString($order){
        return "\nOrder {Id: " . $order->getId()
                    . "\n\tCustomerId: " . $order->getCustomerId()
                    . "\n\tStatus: " . $order->getStatus()
                    . "\n\tState: " . $order->getState()
                    . "\n\tDate: " . date("c",strtotime($order->getCreatedAtDate()))
                    . "\n\tHasShipped: " . $this->hasOrderCompletelyShipped($order)
                    . "\n\tLatestShipmentDate: " . date("c",$this->getLatestShipmentDate($order))
                    . "\n\tNumItems: " . count($order->getAllItems())
                    . "\n\tSentInBVPPEFeed: " . $order->getData(self::ALREADY_SENT_IN_FEED_FLAG)
                    //. "\n\tCustomerEmail: " . $order->getCustomerEmail()    //Don't put CustomerEmail in the logs - could be considered PII
                    . "\n}";
    }

    private function getTriggeringEventDate($order, $triggeringEvent) {
        $timestamp = strtotime($order->getCreatedAtDate());

        if ($triggeringEvent === self::TRIGGER_EVENT_SHIP) {
            $timestamp = $this->getLatestShipmentDate($order);
        }

        return date("c", $timestamp);
    }

    private function getNumDaysLookbackStartDate($numDaysLookback) {
        return date("Y-m-d", strtotime(date("Y-m-d", time()) . " -" . $numDaysLookback . " days"));
    }

    private function getDelayDaysThresholdTimestamp($delayDaysSinceEvent) {
        return time() - (24 * 60 * 60 * $delayDaysSinceEvent);
    }

    private function shouldIncludeOrder($order, $triggeringEvent, $delayDaysSinceEvent) {
        // Have we already included this order in a previous feed?
        if ($order->getData(self::ALREADY_SENT_IN_FEED_FLAG) === "1") {
            Mage::log("    BV - Skipping Order.  Already included in previous feed. " . $this->orderToString($order));
            return false;
        }

        // Is the order canceled?
        if ($order->isCanceled()) {
            Mage::log("    BV - Skipping Order.  Canceled state. " . $this->orderToString($order));
            return false;
        }

        // Ensure that we can get the store for the order
        $store = $order->getStore();
        if (is_null($store)) {
            Mage::log("    BV - Skipping Order.  Could not find store for order. " . $this->orderToString($order));
            return false;
        }

        $thresholdTimestamp = $this->getDelayDaysThresholdTimestamp($delayDaysSinceEvent);

        if ($triggeringEvent === self::TRIGGER_EVENT_SHIP) {
            // We need to see if this order is completely shipped, and if so, is the latest item ship date outside of the delay period.

            // Is the order completely shipped?
            if (!$this->hasOrderCompletelyShipped($order)) {
                Mage::log("    BV - Skipping Order.  Not completely shipped. " . $this->orderToString($order));
                return false;
            }

            // Are we outside of the delay period
            $latestItemShipDateTimestamp = $this->getLatestShipmentDate($order);
            if ($latestItemShipDateTimestamp > $thresholdTimestamp) {
                // Latest ship date for the fully shipped order is still within the delay period
                Mage::log("    BV - Skipping Order.  Ship date not outside the threshold of " . date("c", $thresholdTimestamp) . ". " . $this->orderToString($order));
                return false;
            }
        } else if ($triggeringEvent === self::TRIGGER_EVENT_PURCHASE) {
            // We need to see if the order placement timestamp of this order is outside of the delay period
            $orderPlacementTimestamp = strtotime($order->getCreatedAtDate());
            if ($orderPlacementTimestamp > $thresholdTimestamp) {
                // Order placement date is still within the delay period
                Mage::log("    BV - Skipping Order.  Order date not outside the threshold of " . date("c", $thresholdTimestamp) . ". " . $this->orderToString($order));
                return false;
            }
        }


        // Finally, ensure we have everything on this order that would be needed.

        // Do we have what basically looks like a legit email address?
        if (!preg_match("/@/", $order->getCustomerEmail())) {
            Mage::log("    BV - Skipping Order.  No valid email address. " . $this->orderToString($order));
            return false;
        }

        // Does the order have any items?
        if (count($order->getAllItems()) < 1) {
            Mage::log("    BV - Skipping Order.  No items in this order. " . $this->orderToString($order));
            return false;
        }



        if (self::DEBUG_OUTPUT) {
                Mage::log("    BV - Including Order. " . $this->orderToString($order));
        }
        return true;
    }

    private function hasOrderCompletelyShipped($order) {
        $hasOrderCompletelyShipped = true;
        $items = $order->getAllItems();
        foreach($items as $item) {
            // See /var/www/magento/app/code/core/Mage/Sales/Model/Order/Item.php
            if ($item->getQtyToShip() > 0 && !$item->getIsVirtual() && !$item->getLockedDoShip()) {
                // This item still has qty that needs to ship
                $hasOrderCompletelyShipped = false;
            }
        }
        return $hasOrderCompletelyShipped;
    }

    private function getLatestShipmentDate($order) {
        $latestShipmentTimestamp = 0;

        $shipments = $order->getShipmentsCollection();
        foreach($shipments as $shipment) {
            $latestShipmentTimestamp = max(strtotime($shipment->getCreatedAtDate()), $latestShipmentTimestamp);
        }

        return $latestShipmentTimestamp;  //This should be an int timestamp of num seconds since epoch
    }

}

