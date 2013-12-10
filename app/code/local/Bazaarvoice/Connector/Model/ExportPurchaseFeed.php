<?php
class Bazaarvoice_Connector_Model_ExportPurchaseFeed extends Mage_Core_Model_Abstract
{

    const ALREADY_SENT_IN_FEED_FLAG = 'sent_in_bv_postpurchase_feed';
    const TRIGGER_EVENT_PURCHASE = 'purchase';
    const TRIGGER_EVENT_SHIP = 'ship';

    const NUM_DAYS_LOOKBACK = 30;

    const DEBUG_OUTPUT = false;

    protected function _construct()
    {
    }

    public function exportPurchaseFeed()
    {
        // Log
        Mage::log('Start Bazaarvoice purchase feed generation');
        // Check global setting to see what at which scope / level we should generate feeds
        $feedGenScope = Mage::getStoreConfig('bazaarvoice/feeds/generation_scope');
        switch ($feedGenScope) {
            case Bazaarvoice_Connector_Model_Source_FeedGenerationScope::SCOPE_WEBSITE:
                $this->exportPurchaseFeedByWebsite();
                break;
            case Bazaarvoice_Connector_Model_Source_FeedGenerationScope::SCOPE_STORE_GROUP:
                $this->exportPurchaseFeedByGroup();
                break;
            case Bazaarvoice_Connector_Model_Source_FeedGenerationScope::SCOPE_STORE_VIEW:
                $this->exportPurchaseFeedByStore();
                break;
        }
        // Log
        Mage::log('End Bazaarvoice purchase feed generation');
    }

    /**
     *
     */
    public function exportPurchaseFeedByWebsite()
    {
        // Log
        Mage::log('Exporting purchase feed file for each website...');
        // Iterate through all websites in this instance
        // (Not the 'admin' website / store / view, which represents admin panel)
        $websites = Mage::app()->getWebsites(false);
        /** @var $website Mage_Core_Model_Website */
        foreach ($websites as $website) {
            try {
                if (Mage::getStoreConfig('bazaarvoice/feeds/enable_purchase_feed', $website->getDefaultGroup()->getDefaultStoreId()) === '1'
                    && Mage::getStoreConfig('bazaarvoice/general/enable_bv', $website->getDefaultGroup()->getDefaultStoreId()) === '1'
                ) {
                    if (count($website->getStores()) > 0) {
                        Mage::log('    BV - Exporting purchase feed for website: ' . $website->getName(), Zend_Log::INFO);
                        $this->exportPurchaseFeedForWebsite($website);
                    }
                    else {
                        Mage::throwException('No stores for website: ' . $website->getName());
                    }
                }
                else {
                    Mage::log('    BV - Purchase feed disabled for website: ' . $website->getName(), Zend_Log::INFO);
                }
            }
            catch (Exception $e) {
                Mage::log('    BV - Failed to export daily purchase feed for website: ' . $website->getName(), Zend_Log::ERR);
                Mage::log('    BV - Error message: ' . $e->getMessage(), Zend_Log::ERR);
                Mage::logException($e);
                // Continue processing other websites
            }
        }
    }

    /**
     *
     */
    public function exportPurchaseFeedByGroup()
    {
        // Log
        Mage::log('Exporting purchase feed file for each store group...');
        // Iterate through all stores / groups in this instance
        // (Not the 'admin' store view, which represents admin panel)
        $groups = Mage::app()->getGroups(false);
        /** @var $group Mage_Core_Model_Store_Group */
        foreach ($groups as $group) {
            try {
                if (Mage::getStoreConfig('bazaarvoice/feeds/enable_purchase_feed', $group->getDefaultStoreId()) === '1'
                    && Mage::getStoreConfig('bazaarvoice/general/enable_bv', $group->getDefaultStoreId()) === '1'
                ) {
                    if (count($group->getStores()) > 0) {
                        Mage::log('    BV - Exporting purchase feed for store group: ' . $group->getName(), Zend_Log::INFO);
                        $this->exportPurchaseFeedForStoreGroup($group);
                    }
                    else {
                        Mage::throwException('No stores for store group: ' . $group->getName());
                    }
                }
                else {
                    Mage::log('    BV - Purchase feed disabled for store group: ' . $group->getName(), Zend_Log::INFO);
                }
            }
            catch (Exception $e) {
                Mage::log('    BV - Failed to export daily purchase feed for store group: ' . $group->getName(), Zend_Log::ERR);
                Mage::log('    BV - Error message: ' . $e->getMessage(), Zend_Log::ERR);
                Mage::logException($e);
                // Continue processing other store groups
            }
        }
    }

    /**
     *
     */
    public function exportPurchaseFeedByStore()
    {
        // Log
        Mage::log('Exporting purchase feed file for each store...');
        // Iterate through all stores in this instance
        // (Not the 'admin' store view, which represents admin panel)
        $stores = Mage::app()->getStores(false);
        /** @var $store Mage_Core_Model_Store */
        foreach ($stores as $store) {
            try {
                if (Mage::getStoreConfig('bazaarvoice/feeds/enable_purchase_feed', $store->getId()) === '1'
                    && Mage::getStoreConfig('bazaarvoice/general/enable_bv', $store->getId()) === '1'
                ) {
                        Mage::log('    BV - Exporting purchase feed for: ' . $store->getCode(), Zend_Log::INFO);
                        $this->exportPurchaseFeedForStore($store);
                }
                else {
                    Mage::log('    BV - Purchase feed disabled for store: ' . $store->getCode(), Zend_Log::INFO);
                }
            }
            catch (Exception $e) {
                Mage::log('    BV - Failed to export daily purchase feed for store: ' . $store->getCode(), Zend_Log::ERR);
                Mage::log('    BV - Error message: ' . $e->getMessage(), Zend_Log::ERR);
                Mage::logException($e);
                // Continue processing other stores
            }
        }
    }

    /**
     * @param Mage_Core_Model_Website $website
     */
    public function exportPurchaseFeedForWebsite(Mage_Core_Model_Website $website)
    {
        // Get ref to BV helper
        /* @var $bvHelper Bazaarvoice_Connector_Helper_Data */
        $bvHelper = Mage::helper('bazaarvoice');

        // Build purchase export file path and name
        $purchaseFeedFilePath = Mage::getBaseDir("var") . DS . 'export' . DS . 'bvfeeds';
        $purchaseFeedFileName = 'purchaseFeed-website-' . $website->getId() . '-' . date('U') . '.xml';

        // Make sure that the directory we want to write to exists.
        $ioObject = new Varien_Io_File();
        try {
            $ioObject->open(array('path' => $purchaseFeedFilePath));
        }
        catch (Exception $e) {
            $ioObject->mkdir($purchaseFeedFilePath, 0777, true);
            $ioObject->open(array('path' => $purchaseFeedFilePath));
        }

        if ($ioObject->streamOpen($purchaseFeedFileName)) {

            $ioObject->streamWrite("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<Feed xmlns=\"http://www.bazaarvoice.com/xs/PRR/PostPurchaseFeed/4.9\">\n");

            Mage::log('    BV - processing all orders');
            $numOrdersExported = $this->processOrdersForWebsite($ioObject, $website);
            Mage::log('    BV - completed processing all orders');

            $ioObject->streamWrite("</Feed>\n");
            $ioObject->streamClose();

            // Don't bother uploading if there are no orders in the feed
            $upload = false;
            if ($numOrdersExported > 0) {
                /*
                 * Hard code path and file name
                 * Former config setting defaults:
                 *   <ExportPath>/ppe/inbox</ExportPath>
                 *   <ExportFileName>bazaarvoice-order-data.xml</ExportFileName>
                 */
                $destinationFile = '/ppe/inbox/bazaarvoice-order-data-' . date('U') . '.xml';
                $sourceFile = $purchaseFeedFilePath . DS . $purchaseFeedFileName;

                $upload = $bvHelper->uploadFile($sourceFile, $destinationFile, $website->getDefaultStore());
            }

            if (!$upload) {
                Mage::log('    Bazaarvoice FTP upload failed! [filename = ' . $purchaseFeedFileName . ']');
            }
            else {
                Mage::log('    Bazaarvoice FTP upload success! [filename = ' . $purchaseFeedFileName . ']');
                $ioObject->rm($purchaseFeedFileName);
            }

        }
    }

    /**
     *
     * @param Mage_Core_Model_Store_Group $group Store Group
     */
    public function exportPurchaseFeedForStoreGroup(Mage_Core_Model_Store_Group $group)
    {
        // Get ref to BV helper
        /* @var $bvHelper Bazaarvoice_Connector_Helper_Data */
        $bvHelper = Mage::helper('bazaarvoice');

        // Build purchase export file path and name
        $purchaseFeedFilePath = Mage::getBaseDir("var") . DS . 'export' . DS . 'bvfeeds';
        $purchaseFeedFileName = 'purchaseFeed-group-' . $group->getId() . '-' . date('U') . '.xml';

        // Make sure that the directory we want to write to exists.
        $ioObject = new Varien_Io_File();
        try {
            $ioObject->open(array('path' => $purchaseFeedFilePath));
        }
        catch (Exception $e) {
            $ioObject->mkdir($purchaseFeedFilePath, 0777, true);
            $ioObject->open(array('path' => $purchaseFeedFilePath));
        }

        if ($ioObject->streamOpen($purchaseFeedFileName)) {

            $ioObject->streamWrite("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<Feed xmlns=\"http://www.bazaarvoice.com/xs/PRR/PostPurchaseFeed/4.9\">\n");

            Mage::log('    BV - processing all orders');
            $numOrdersExported = $this->processOrdersForGroup($ioObject, $group);
            Mage::log('    BV - completed processing all orders');

            $ioObject->streamWrite("</Feed>\n");
            $ioObject->streamClose();

            // Don't bother uploading if there are no orders in the feed
            $upload = false;
            if ($numOrdersExported > 0) {
                /*
                 * Hard code path and file name
                 * Former config setting defaults:
                 *   <ExportPath>/ppe/inbox</ExportPath>
                 *   <ExportFileName>bazaarvoice-order-data.xml</ExportFileName>
                 */
                $destinationFile = '/ppe/inbox/bazaarvoice-order-data-' . date('U') . '.xml';
                $sourceFile = $purchaseFeedFilePath . DS . $purchaseFeedFileName;

                $upload = $bvHelper->uploadFile($sourceFile, $destinationFile, $group->getDefaultStore());
            }

            if (!$upload) {
                Mage::log('    Bazaarvoice FTP upload failed! [filename = ' . $purchaseFeedFileName . ']');
            }
            else {
                Mage::log('    Bazaarvoice FTP upload success! [filename = ' . $purchaseFeedFileName . ']');
                $ioObject->rm($purchaseFeedFileName);
            }

        }
    }

    /**
     * @param Mage_Core_Model_Store $store
     */
    public function exportPurchaseFeedForStore(Mage_Core_Model_Store $store)
    {
        // Get ref to BV helper
        /* @var $bvHelper Bazaarvoice_Connector_Helper_Data */
        $bvHelper = Mage::helper('bazaarvoice');

        // Build purchase export file path and name
        $purchaseFeedFilePath = Mage::getBaseDir('var') . DS . 'export' . DS . 'bvfeeds';
        $purchaseFeedFileName = 'purchaseFeed-store-' . $store->getId() . '-' . date('U') . '.xml';

        // Make sure that the directory we want to write to exists.
        $ioObject = new Varien_Io_File();
        try {
            $ioObject->open(array('path' => $purchaseFeedFilePath));
        }
        catch (Exception $e) {
            $ioObject->mkdir($purchaseFeedFilePath, 0777, true);
            $ioObject->open(array('path' => $purchaseFeedFilePath));
        }

        if ($ioObject->streamOpen($purchaseFeedFileName)) {

            $ioObject->streamWrite("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<Feed xmlns=\"http://www.bazaarvoice.com/xs/PRR/PostPurchaseFeed/4.9\">\n");

            Mage::log("    BV - processing all orders");
            $numOrdersExported = $this->processOrdersForStore($ioObject, $store);
            Mage::log("    BV - completed processing all orders");

            $ioObject->streamWrite("</Feed>\n");
            $ioObject->streamClose();

            // Don't bother uploading if there are no orders in the feed
            $upload = false;
            if ($numOrdersExported > 0) {
                /*
                 * Hard code path and file name
                 * Former config setting defaults:
                 *   <ExportPath>/ppe/inbox</ExportPath>
                 *   <ExportFileName>bazaarvoice-order-data.xml</ExportFileName>
                 */
                $destinationFile = '/ppe/inbox/bazaarvoice-order-data-' . date('U') . '.xml';
                $sourceFile = $purchaseFeedFilePath . DS . $purchaseFeedFileName;

                $upload = $bvHelper->uploadFile($sourceFile, $destinationFile, $store);
            }

            if (!$upload) {
                Mage::log('    Bazaarvoice FTP upload failed! [filename = ' . $purchaseFeedFileName . ']');
            }
            else {
                Mage::log('    Bazaarvoice FTP upload success! [filename = ' . $purchaseFeedFileName . ']');
                $ioObject->rm($purchaseFeedFileName);
            }

        }
    }

    /**
     * @param Varien_Io_File $ioObject
     * @param Mage_Core_Model_Website $website
     * @return int
     */
    private function processOrdersForWebsite(Varien_Io_File $ioObject, Mage_Core_Model_Website $website)
    {
        // Get a collection of all the orders
        $orders = Mage::getModel('sales/order')->getCollection();

        // Filter the returned orders to minimize processing as much as possible.  More available operations in method _getConditionSql in Varien_Data_Collection_Db.
        // Add filter to limit orders to this store group
        // Join to core_store table and grab website_id field
        $orders->getSelect()
            ->joinLeft('core_store', 'main_table.store_id = core_store.store_id', 'core_store.website_id')
            ->where('core_store.website_id = ' . $website->getId());
        // Status is 'complete' or 'closed'
        $orders->addFieldToFilter('status', array(
            'in' => array(
                'complete',
                'closed'
            )
        ));
        // Only orders created within our look-back window
        $orders->addFieldToFilter('created_at', array('gteq' => $this->getNumDaysLookbackStartDate()));
        // Exclude orders that have been previously sent in a feed
        $orders->addFieldToFilter(self::ALREADY_SENT_IN_FEED_FLAG, array('null' => 'null')); // adds an 'IS NULL' filter to the BV flag column

        // Write orders to file
        $numOrdersExported = $this->writeOrdersToFile($ioObject, $orders);

        return $numOrdersExported;
    }

    /**
     * @param Varien_Io_File $ioObject
     * @param Mage_Core_Model_Store_Group $group
     * @return int
     */
    private function processOrdersForGroup(Varien_Io_File $ioObject, Mage_Core_Model_Store_Group $group)
    {
        // Get a collection of all the orders
        $orders = Mage::getModel('sales/order')->getCollection();

        // Filter the returned orders to minimize processing as much as possible.  More available operations in method _getConditionSql in Varien_Data_Collection_Db.
        // Add filter to limit orders to this store group
        // Join to core_store table and grab group_id field
        $orders->getSelect()
            ->joinLeft('core_store', 'main_table.store_id = core_store.store_id', 'core_store.group_id')
            ->where('core_store.group_id = ' . $group->getId());
        // Status is 'complete' or 'closed'
        $orders->addFieldToFilter('status', array(
            'in' => array(
                'complete',
                'closed'
            )
        ));
        // Only orders created within our look-back window
        $orders->addFieldToFilter('created_at', array('gteq' => $this->getNumDaysLookbackStartDate()));
        // Exclude orders that have been previously sent in a feed
        $orders->addFieldToFilter(self::ALREADY_SENT_IN_FEED_FLAG, array('null' => 'null')); // adds an 'IS NULL' filter to the BV flag column

        // Write orders to file
        $numOrdersExported = $this->writeOrdersToFile($ioObject, $orders);

        return $numOrdersExported;
    }

    /**
     * @param Varien_Io_File $ioObject
     * @param Mage_Core_Model_Store $store
     * @return int
     */
    private function processOrdersForStore(Varien_Io_File $ioObject, Mage_Core_Model_Store $store)
    {
        // Get a collection of all the orders
        $orders = Mage::getModel('sales/order')->getCollection();

        // Filter the returned orders to minimize processing as much as possible.  More available operations in method _getConditionSql in Varien_Data_Collection_Db.
        // Add filter to limit orders to this store
        $orders->addFieldToFilter('store_id', $store->getId());
        // Status is 'complete' or 'closed'
        $orders->addFieldToFilter('status', array(
            'in' => array(
                'complete',
                'closed'
            )
        ));
        // Only orders created within our look-back window
        $orders->addFieldToFilter('created_at', array('gteq' => $this->getNumDaysLookbackStartDate()));
        // Exclude orders that have been previously sent in a feed
        $orders->addFieldToFilter(self::ALREADY_SENT_IN_FEED_FLAG, array('null' => 'null')); // adds an 'IS NULL' filter to the BV flag column

        // Write orders to file
        $numOrdersExported = $this->writeOrdersToFile($ioObject, $orders);

        return $numOrdersExported;
    }

    /**
     * @param Varien_Io_File $ioObject
     * @param $orders
     * @return int
     */
    private function writeOrdersToFile(Varien_Io_File $ioObject, $orders)
    {
        // Get ref to BV helper
        /* @var $bvHelper Bazaarvoice_Connector_Helper_Data */
        $bvHelper = Mage::helper('bazaarvoice');

        // Initialize references to the object model accessors
        $orderModel = Mage::getModel('sales/order');

        // Gather settings for how this feed should be generated
        $triggeringEvent = Mage::getStoreConfig('bazaarvoice/feeds/triggering_event') ===
        Bazaarvoice_Connector_Model_Source_TriggeringEvent::SHIPPING ? self::TRIGGER_EVENT_SHIP : self::TRIGGER_EVENT_PURCHASE;
        // Hard code former settings
        $delayDaysSinceEvent = 1;
        Mage::log("    BV - Config {triggering_event: " . $triggeringEvent
        . ", NumDaysLookback: " . self::NUM_DAYS_LOOKBACK
        . ", NumDaysLookbackStartDate: " . $this->getNumDaysLookbackStartDate()
        . ", DelayDaysSinceEvent: " . $delayDaysSinceEvent
        . ', DelayDaysThreshold: ' . date('c', $this->getDelayDaysThresholdTimestamp($delayDaysSinceEvent)) . '}');

        $numOrdersExported = 0; // Keep track of how many orders we include in the feed

        foreach ($orders->getAllIds() as $orderId) {

            /* @var $order Mage_Sales_Model_Order */
            $order = $orderModel->load($orderId);
            $store = $order->getStore();

            if (!$this->shouldIncludeOrder($order, $triggeringEvent, $delayDaysSinceEvent)) {
                continue;
            }

            $numOrdersExported++;

            $ioObject->streamWrite("<Interaction>\n");
            $ioObject->streamWrite('    <EmailAddress>' . $order->getCustomerEmail() . "</EmailAddress>\n");
            $ioObject->streamWrite('    <Locale>' . $store->getConfig('bazaarvoice/general/locale') . "</Locale>\n");
            $ioObject->streamWrite('    <UserName>' . $order->getCustomerName() . "</UserName>\n");
            $ioObject->streamWrite('    <UserID>' . $order->getCustomerId() . "</UserID>\n");
            $ioObject->streamWrite('    <TransactionDate>' . $this->getTriggeringEventDate($order, $triggeringEvent) .
            "</TransactionDate>\n");
            $ioObject->streamWrite("    <Products>\n");
            /* @var $item Mage_Sales_Model_Order_Item */
            foreach ($order->getAllVisibleItems() as $item) {
                $product = $bvHelper->getReviewableProductFromOrderItem($item);
                if (!is_null($product)) {
                    $ioObject->streamWrite("        <Product>\n");
                    $ioObject->streamWrite('            <ExternalId>' . $bvHelper->getProductId($product) .
                    "</ExternalId>\n");
                    $ioObject->streamWrite('            <Name>' . htmlspecialchars($product->getName(), ENT_QUOTES, 'UTF-8') .
                    "</Name>\n");
                    $ioObject->streamWrite('            <ImageUrl>' . $product->getImageUrl() . "</ImageUrl>\n");
                    $ioObject->streamWrite('            <Price>' . number_format((float)$item->getOriginalPrice(), 2) . "</Price>\n");
                    $ioObject->streamWrite("        </Product>\n");
                }
            }
            $ioObject->streamWrite("    </Products>\n");
            $ioObject->streamWrite("</Interaction>\n");

            $order->setData(self::ALREADY_SENT_IN_FEED_FLAG, 1);
            $order->save();
            $order->reset(); // Forces a reload of various collections that the object caches internally so that the next time we load from the orderModel, we'll get a completely new object.

        }

        return $numOrdersExported;
    }

    private function orderToString(Mage_Sales_Model_Order $order)
    {
        return "\nOrder {Id: " . $order->getId()
        . "\n\tCustomerId: " . $order->getCustomerId()
        . "\n\tStatus: " . $order->getStatus()
        . "\n\tState: " . $order->getState()
        . "\n\tDate: " . date('c', strtotime($order->getCreatedAtDate()))
        . "\n\tHasShipped: " . $this->hasOrderCompletelyShipped($order)
        . "\n\tLatestShipmentDate: " . date('c', $this->getLatestShipmentDate($order))
        . "\n\tNumItems: " . count($order->getAllItems())
        . "\n\tSentInBVPPEFeed: " . $order->getData(self::ALREADY_SENT_IN_FEED_FLAG)
        // . "\n\tCustomerEmail: " . $order->getCustomerEmail()    // Don't put CustomerEmail in the logs - could be considered PII
        . "\n}";
    }

    private function getTriggeringEventDate(Mage_Sales_Model_Order $order, $triggeringEvent)
    {
        $timestamp = strtotime($order->getCreatedAtDate());

        if ($triggeringEvent === self::TRIGGER_EVENT_SHIP) {
            $timestamp = $this->getLatestShipmentDate($order);
        }

        return date('c', $timestamp);
    }

    private function getNumDaysLookbackStartDate()
    {
        return date('Y-m-d', strtotime(date('Y-m-d', time()) . ' -' . self::NUM_DAYS_LOOKBACK . ' days'));
    }

    private function getDelayDaysThresholdTimestamp($delayDaysSinceEvent)
    {
        return time() - (24 * 60 * 60 * $delayDaysSinceEvent);
    }

    private function shouldIncludeOrder(Mage_Sales_Model_Order $order, $triggeringEvent, $delayDaysSinceEvent)
    {
        // Have we already included this order in a previous feed?
        if ($order->getData(self::ALREADY_SENT_IN_FEED_FLAG) === '1') {
            Mage::log('    BV - Skipping Order.  Already included in previous feed. ' . $this->orderToString($order));
            return false;
        }

        // Is the order canceled?
        if ($order->isCanceled()) {
            Mage::log('    BV - Skipping Order.  Canceled state. ' . $this->orderToString($order));
            return false;
        }

        // Ensure that we can get the store for the order
        $store = $order->getStore();
        if (is_null($store)) {
            Mage::log('    BV - Skipping Order.  Could not find store for order. ' . $this->orderToString($order));
            return false;
        }

        $thresholdTimestamp = $this->getDelayDaysThresholdTimestamp($delayDaysSinceEvent);

        if ($triggeringEvent === self::TRIGGER_EVENT_SHIP) {
            // We need to see if this order is completely shipped, and if so, is the latest item ship date outside of the delay period.

            // Is the order completely shipped?
            if (!$this->hasOrderCompletelyShipped($order)) {
                Mage::log('    BV - Skipping Order.  Not completely shipped. ' . $this->orderToString($order));
                return false;
            }

            // Are we outside of the delay period
            $latestItemShipDateTimestamp = $this->getLatestShipmentDate($order);
            if ($latestItemShipDateTimestamp > $thresholdTimestamp) {
                // Latest ship date for the fully shipped order is still within the delay period
                Mage::log('    BV - Skipping Order.  Ship date not outside the threshold of ' . date('c', $thresholdTimestamp) . '. ' .
                $this->orderToString($order));
                return false;
            }
        }
        else {
            if ($triggeringEvent === self::TRIGGER_EVENT_PURCHASE) {
                // We need to see if the order placement timestamp of this order is outside of the delay period
                $orderPlacementTimestamp = strtotime($order->getCreatedAtDate());
                if ($orderPlacementTimestamp > $thresholdTimestamp) {
                    // Order placement date is still within the delay period
                    Mage::log('    BV - Skipping Order.  Order date not outside the threshold of ' . date('c', $thresholdTimestamp) .
                    '. ' .
                    $this->orderToString($order));
                    return false;
                }
            }
        }


        // Finally, ensure we have everything on this order that would be needed.

        // Do we have what basically looks like a legit email address?
        if (!preg_match('/@/', $order->getCustomerEmail())) {
            Mage::log('    BV - Skipping Order.  No valid email address. ' . $this->orderToString($order));
            return false;
        }

        // Does the order have any items?
        if (count($order->getAllItems()) < 1) {
            Mage::log('    BV - Skipping Order.  No items in this order. ' . $this->orderToString($order));
            return false;
        }


        if (self::DEBUG_OUTPUT) {
            Mage::log('    BV - Including Order. ' . $this->orderToString($order));
        }
        return true;
    }

    private function hasOrderCompletelyShipped(Mage_Sales_Model_Order $order)
    {
        $hasOrderCompletelyShipped = true;
        $items = $order->getAllItems();
        /* @var $item Mage_Sales_Model_Order_Item */
        foreach ($items as $item) {
            // See /var/www/magento/app/code/core/Mage/Sales/Model/Order/Item.php
            if ($item->getQtyToShip() > 0 && !$item->getIsVirtual() && !$item->getLockedDoShip()) {
                // This item still has qty that needs to ship
                $hasOrderCompletelyShipped = false;
            }
        }
        return $hasOrderCompletelyShipped;
    }

    private function getLatestShipmentDate(Mage_Sales_Model_Order $order)
    {
        $latestShipmentTimestamp = 0;

        $shipments = $order->getShipmentsCollection();
        /* @var $shipment Mage_Sales_Model_Order_Shipment */
        foreach ($shipments as $shipment) {
            $latestShipmentTimestamp = max(strtotime($shipment->getCreatedAtDate()), $latestShipmentTimestamp);
        }

        return $latestShipmentTimestamp; // This should be an int timestamp of num seconds since epoch
    }

}

