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
        Mage::log('    BV - Start purchase feed generation', Zend_Log::INFO, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
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
        Mage::log('    BV - End purchase feed generation', Zend_Log::INFO, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
    }

    /**
     *
     */
    public function exportPurchaseFeedByWebsite()
    {
        // Log
        Mage::log('    BV - Exporting purchase feed file for each website...', Zend_Log::INFO, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
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
                        Mage::log('    BV - Exporting purchase feed for website: ' . $website->getName(), Zend_Log::INFO, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
                        $this->exportPurchaseFeedForWebsite($website);
                    }
                    else {
                        Mage::throwException('No stores for website: ' . $website->getName());
                    }
                }
                else {
                    Mage::log('    BV - Purchase feed disabled for website: ' . $website->getName(), Zend_Log::INFO, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
                }
            }
            catch (Exception $e) {
                Mage::log('     BV - Failed to export daily purchase feed for website: ' . $website->getName(), Zend_Log::ERR, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
                Mage::log('     BV - Error message: ' . $e->getMessage(), Zend_Log::ERR, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
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
        Mage::log('    BV - Exporting purchase feed file for each store group...', Zend_Log::INFO, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
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
                        Mage::log('    BV - Exporting purchase feed for store group: ' . $group->getName(), Zend_Log::INFO, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
                        $this->exportPurchaseFeedForStoreGroup($group);
                    }
                    else {
                        Mage::throwException('No stores for store group: ' . $group->getName());
                    }
                }
                else {
                    Mage::log('    BV - Purchase feed disabled for store group: ' . $group->getName(), Zend_Log::INFO, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
                }
            }
            catch (Exception $e) {
                Mage::log('    BV - Failed to export daily purchase feed for store group: ' . $group->getName(), Zend_Log::ERR, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
                Mage::log('    BV - Error message: ' . $e->getMessage(), Zend_Log::ERR, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
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
        Mage::log('    BV - Exporting purchase feed file for each store...', Zend_Log::INFO, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
        // Iterate through all stores in this instance
        // (Not the 'admin' store view, which represents admin panel)
        $stores = Mage::app()->getStores(false);
        /** @var $store Mage_Core_Model_Store */
        foreach ($stores as $store) {
            try {
                if (Mage::getStoreConfig('bazaarvoice/feeds/enable_purchase_feed', $store->getId()) === '1'
                    && Mage::getStoreConfig('bazaarvoice/general/enable_bv', $store->getId()) === '1'
                ) {
                        Mage::log('    BV - Exporting purchase feed for: ' . $store->getCode(), Zend_Log::INFO, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
                        $this->exportPurchaseFeedForStore($store);
                }
                else {
                    Mage::log('    BV - Purchase feed disabled for store: ' . $store->getCode(), Zend_Log::INFO, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
                }
            }
            catch (Exception $e) {
                Mage::log('    BV - Failed to export daily purchase feed for store: ' . $store->getCode(), Zend_Log::ERR, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
                Mage::log('    BV - Error message: ' . $e->getMessage(), Zend_Log::ERR, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
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

            Mage::log('    BV - processing all orders', Zend_Log::INFO, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
            $ordersExported = $this->processOrdersForWebsite($ioObject, $website);
            $this->flagOrders($ordersExported, 1);
            Mage::log('    BV - completed processing all orders', Zend_Log::INFO, Bazaarvoice_Connector_Helper_Data::LOG_FILE);

            $ioObject->streamWrite("</Feed>\n");
            $ioObject->streamClose();

            // Don't bother uploading if there are no orders in the feed
            $upload = false;
            if (count($ordersExported) > 0) {
                /*
                 * Hard code path and file name
                 * Former config setting defaults:
                 *   <ExportPath>/ppe/inbox</ExportPath>
                 *   <ExportFileName>bv_ppe_tag_feed-magento.xml</ExportFileName>
                 */
                $destinationFile = '/ppe/inbox/bv_ppe_tag_feed-magento-' . date('U') . '.xml';
                $sourceFile = $purchaseFeedFilePath . DS . $purchaseFeedFileName;

                $upload = $bvHelper->uploadFile($sourceFile, $destinationFile, $website->getDefaultStore());
            }

            if (!$upload) {
                Mage::log('     BVSFTP - upload failed! [filename = ' . $purchaseFeedFileName . ']', Zend_Log::ERR, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
            }
            else {
                Mage::log('    BVSFTP - upload success! [filename = ' . $purchaseFeedFileName . ']', Zend_Log::INFO, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
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

            Mage::log('    BV - processing all orders', Zend_Log::INFO, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
            $ordersExported = $this->processOrdersForGroup($ioObject, $group);
            $this->flagOrders($ordersExported, 1);
            Mage::log('    BV - completed processing all orders', Zend_Log::INFO, Bazaarvoice_Connector_Helper_Data::LOG_FILE);

            $ioObject->streamWrite("</Feed>\n");
            $ioObject->streamClose();

            // Don't bother uploading if there are no orders in the feed
            $upload = false;
            if (count($ordersExported) > 0) {
                /*
                 * Hard code path and file name
                 * Former config setting defaults:
                 *   <ExportPath>/ppe/inbox</ExportPath>
                 *   <ExportFileName>bv_ppe_tag_feed-magento.xml</ExportFileName>
                 */
                $destinationFile = '/ppe/inbox/bv_ppe_tag_feed-magento-' . date('U') . '.xml';
                $sourceFile = $purchaseFeedFilePath . DS . $purchaseFeedFileName;

                $upload = $bvHelper->uploadFile($sourceFile, $destinationFile, $group->getDefaultStore());
            }

            if (!$upload) {
                Mage::log('     BVSFTP - upload failed! [filename = ' . $purchaseFeedFileName . ']', Zend_Log::ERR, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
            }
            else {
                Mage::log('    BVSFTP - upload success! [filename = ' . $purchaseFeedFileName . ']', Zend_Log::INFO, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
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

            Mage::log("    BV - processing all orders", Zend_Log::INFO, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
            $ordersExported = $this->processOrdersForStore($ioObject, $store);
            $this->flagOrders($ordersExported, 1);
            Mage::log("    BV - completed processing all orders", Zend_Log::INFO, Bazaarvoice_Connector_Helper_Data::LOG_FILE);

            $ioObject->streamWrite("</Feed>\n");
            $ioObject->streamClose();

            // Don't bother uploading if there are no orders in the feed
            $upload = false;
            if (count($ordersExported) > 0) {
                /*
                 * Hard code path and file name
                 * Former config setting defaults:
                 *   <ExportPath>/ppe/inbox</ExportPath>
                 *   <ExportFileName>bv_ppe_tag_feed-magento.xml</ExportFileName>
                 */
                $destinationFile = '/ppe/inbox/bv_ppe_tag_feed-magento-' . date('U') . '.xml';
                $sourceFile = $purchaseFeedFilePath . DS . $purchaseFeedFileName;

                $upload = $bvHelper->uploadFile($sourceFile, $destinationFile, $store);
            }

            if (!$upload) {
                Mage::log('     BVSFTP - upload failed! [filename = ' . $purchaseFeedFileName . ']', Zend_Log::ERR, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
            }
            else {
                Mage::log('    BVSFTP - upload success! [filename = ' . $purchaseFeedFileName . ']', Zend_Log::INFO, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
                $ioObject->rm($purchaseFeedFileName);
            }

        }
    }

    /**
     * @param Varien_Io_File $ioObject
     * @param Mage_Core_Model_Website $website
     * @return int
     */
    protected function processOrdersForWebsite(Varien_Io_File $ioObject, Mage_Core_Model_Website $website)
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
        // Include only orders that have not been sent or have errored out
        $orders->addFieldToFilter(
            array(self::ALREADY_SENT_IN_FEED_FLAG, self::ALREADY_SENT_IN_FEED_FLAG),
            array(
                array('neq' => 1),
                array('null' => 'null')
            )
        ); 

        // Write orders to file
        $ordersExported = $this->writeOrdersToFile($ioObject, $orders);

        return $ordersExported;
    }

    /**
     * @param Varien_Io_File $ioObject
     * @param Mage_Core_Model_Store_Group $group
     * @return int
     */
    protected function processOrdersForGroup(Varien_Io_File $ioObject, Mage_Core_Model_Store_Group $group)
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
        // Include only orders that have not been sent or have errored out
        $orders->addFieldToFilter(
            array(self::ALREADY_SENT_IN_FEED_FLAG, self::ALREADY_SENT_IN_FEED_FLAG),
            array(
                array('neq' => 1),
                array('null' => 'null')
            )
        );

        // Write orders to file
        $ordersExported = $this->writeOrdersToFile($ioObject, $orders);

        return $ordersExported;
    }

    /**
     * @param Varien_Io_File $ioObject
     * @param Mage_Core_Model_Store $store
     * @return int
     */
    protected function processOrdersForStore(Varien_Io_File $ioObject, Mage_Core_Model_Store $store)
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
        // Include only orders that have not been sent or have errored out
        $orders->addFieldToFilter(
            array(self::ALREADY_SENT_IN_FEED_FLAG, self::ALREADY_SENT_IN_FEED_FLAG),
            array(
                array('neq' => 1),
                array('null' => 'null')
            )
        );
        // Write orders to file
        $ordersExported = $this->writeOrdersToFile($ioObject, $orders);

        return $ordersExported;
    }

    /**
     * @param Varien_Io_File $ioObject
     * @param $orders
     * @return int
     */
    protected function writeOrdersToFile(Varien_Io_File $ioObject, $orders)
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
        . ', DelayDaysThreshold: ' . date('c', $this->getDelayDaysThresholdTimestamp($delayDaysSinceEvent)) . '}', Zend_Log::INFO, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
        
        $ordersToExport = array();
        foreach ($orders->getAllIds() as $orderId) {
            $order = $orderModel->load($orderId);
            if (!$this->shouldIncludeOrder($order, $triggeringEvent, $delayDaysSinceEvent)) {
                continue;
            }
            $ordersToExport[] = $orderId;
        }
        Mage::log("    BV - Found " . count($ordersToExport) . " orders to export.", Zend_Log::INFO, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
        
        $exportedOrders = array(); // Keep track of how many orders we include in the feed
        
        foreach ($ordersToExport as $orderId) {
            try{
                /* @var $order Mage_Sales_Model_Order */
                $order = $orderModel->load($orderId);
                $store = $order->getStore();
                
                
                $orderXml = '';
                
                $orderXml .= "<Interaction>\n";
//                $orderXml .= '    <OrderID>' . $order->getIncrementId() . "</OrderID>\n";
                $orderXml .= '    <EmailAddress>' . $order->getCustomerEmail() . "</EmailAddress>\n";
                $orderXml .= '    <Nickname>' . $order->getCustomerFirstname() . "</Nickname>\n";
                $orderXml .= '    <Locale>' . $store->getConfig('bazaarvoice/general/locale') . "</Locale>\n";
                $orderXml .= '    <UserName>' . $order->getCustomerName() . "</UserName>\n";
                if($order->getCustomerId()) {
                    $userId = $order->getCustomerId();
                } else {
                    $userId = md5($order->getCustomerEmail());
                }
                $orderXml .= '    <UserID>' . $userId . "</UserID>\n";
                $orderXml .= '    <TransactionDate>' . $this->getTriggeringEventDate($order, $triggeringEvent) . "</TransactionDate>\n";
                $orderXml .= "    <Products>\n";
                // if families are enabled, get all items
                if(Mage::getStoreConfig('bazaarvoice/feeds/families')){
                    $items = $order->getAllItems();
                } else {
                    $items = $order->getAllVisibleItems();
                }     
                /* @var $item Mage_Sales_Model_Order_Item */
                foreach ($items as $item) {
                    // skip configurable items if families are enabled
                    if(Mage::getStoreConfig('bazaarvoice/feeds/families') && $item->getProduct()->getTypeId() == Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE) continue;
                    
                    $product = $bvHelper->getReviewableProductFromOrderItem($item);
                    
                    // skip disabled products
                    //if($product->getStatus() != Mage_Catalog_Model_Product_Status::STATUS_ENABLED) continue;
                    
                    if (!is_null($product)) {
                        $productXml = '';
                        $productXml .= "        <Product>\n";
                        $productXml .= '            <ExternalId>' . $bvHelper->getProductId($product) .
                        "</ExternalId>\n";
                        $productXml .= '            <Name>' . htmlspecialchars($product->getName(), ENT_QUOTES, 'UTF-8', false) . "</Name>\n";
                        
                        $imageUrl = $product->getImageUrl();
                        $originalPrice = $item->getOriginalPrice();
                        if(Mage::getStoreConfig('bazaarvoice/feeds/families') && $item->getParentItem()) {
                            $parentItem = $item->getParentItem();
                            $parent = Mage::getModel('catalog/product')->load($parentItem->getProductId());
    
                            if(strpos($imageUrl, "placeholder/image.jpg")){
                                // if product families are enabled and product has no image, use configurable image
                                $imageUrl = $parent->getImageUrl();
                            }
                            // also get price from parent item
                            $originalPrice = $parentItem->getOriginalPrice();
                        }   
                        
                        $productXml .= '            <ImageUrl>' . $imageUrl . "</ImageUrl>\n";
                        $productXml .= '            <Price>' . number_format((float)$originalPrice, 2) . "</Price>\n";
                        $productXml .= "        </Product>\n";
                        
                        $orderXml .= $productXml;
                    }
                }
                $orderXml .= "    </Products>\n";
                $orderXml .= "</Interaction>\n";
                $ioObject->streamWrite($orderXml);
                $exportedOrders[] = $orderId;
            } Catch (Exception $e) {
                $this->flagOrders(array($orderId), 2);
            	Mage::log($e->getMessage()."\n".$e->getTraceAsString());
            }

        }
        Mage::log("    BV - Exported " . count($exportedOrders) . " orders.", Zend_Log::INFO, Bazaarvoice_Connector_Helper_Data::LOG_FILE);

        return $exportedOrders;
    }
    
    private function flagOrders($orders, $flag)
    {
        if(count($orders)){
            $resource = Mage::getSingleton('core/resource');
            $writeConnection = $resource->getConnection('core_write');
            $writeConnection->query("UPDATE `" . $resource->getTableName('sales/order') . "` SET `" . self::ALREADY_SENT_IN_FEED_FLAG . "` = " . $flag . " WHERE `entity_id` IN(" . implode(',', $orders) . ");");
        }
    }

    protected function orderToString(Mage_Sales_Model_Order $order)
    {
        return "\nOrder {Id: " . $order->getIncrementId()
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

    protected function getTriggeringEventDate(Mage_Sales_Model_Order $order, $triggeringEvent)
    {
        $timestamp = strtotime($order->getCreatedAtDate());

        if ($triggeringEvent === self::TRIGGER_EVENT_SHIP) {
            $timestamp = $this->getLatestShipmentDate($order);
        }

        return date('c', $timestamp);
    }

    protected function getNumDaysLookbackStartDate()
    {
        return date('Y-m-d', strtotime(date('Y-m-d', time()) . ' -' . self::NUM_DAYS_LOOKBACK . ' days'));
    }

    protected function getDelayDaysThresholdTimestamp($delayDaysSinceEvent)
    {
        return time() - (24 * 60 * 60 * $delayDaysSinceEvent);
    }

    protected function shouldIncludeOrder(Mage_Sales_Model_Order $order, $triggeringEvent, $delayDaysSinceEvent)
    {
        // Have we already included this order in a previous feed?
        if ($order->getData(self::ALREADY_SENT_IN_FEED_FLAG) === '1') {
            Mage::log('    BV - Skipping Order.  Already included in previous feed. ' . $this->orderToString($order), Zend_Log::DEBUG, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
            return false;
        }

        // Is the order canceled?
        if ($order->isCanceled()) {
            Mage::log('    BV - Skipping Order.  Canceled state. ' . $this->orderToString($order), Zend_Log::DEBUG, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
            return false;
        }

        // Ensure that we can get the store for the order
        $store = $order->getStore();
        if (is_null($store)) {
            Mage::log('    BV - Skipping Order.  Could not find store for order. ' . $this->orderToString($order), Zend_Log::DEBUG, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
            return false;
        }

        $thresholdTimestamp = $this->getDelayDaysThresholdTimestamp($delayDaysSinceEvent);

        if ($triggeringEvent === self::TRIGGER_EVENT_SHIP) {
            // We need to see if this order is completely shipped, and if so, is the latest item ship date outside of the delay period.

            // Is the order completely shipped?
            if (!$this->hasOrderCompletelyShipped($order)) {
                Mage::log('    BV - Skipping Order.  Not completely shipped. ' . $this->orderToString($order), Zend_Log::DEBUG, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
                return false;
            }

            // Are we outside of the delay period
            $latestItemShipDateTimestamp = $this->getLatestShipmentDate($order);
            if ($latestItemShipDateTimestamp > $thresholdTimestamp) {
                // Latest ship date for the fully shipped order is still within the delay period
                Mage::log('    BV - Skipping Order.  Ship date not outside the threshold of ' . date('c', $thresholdTimestamp) . '. ' .
                $this->orderToString($order), Zend_Log::DEBUG, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
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
                    $this->orderToString($order), Zend_Log::DEBUG, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
                    return false;
                }
            }
        }


        // Finally, ensure we have everything on this order that would be needed.

        // Do we have what basically looks like a legit email address?
        if (!preg_match('/@/', $order->getCustomerEmail())) {
            Mage::log('    BV - Skipping Order.  No valid email address. ' . $this->orderToString($order), Zend_Log::DEBUG, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
            return false;
        }

        // Does the order have any items?
        if (count($order->getAllItems()) < 1) {
            Mage::log('    BV - Skipping Order.  No items in this order. ' . $this->orderToString($order), Zend_Log::DEBUG, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
            return false;
        }


        if (self::DEBUG_OUTPUT) {
            Mage::log('    BV - Including Order. ' . $this->orderToString($order), Zend_Log::DEBUG, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
        }
        return true;
    }

    protected function hasOrderCompletelyShipped(Mage_Sales_Model_Order $order)
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

    protected function getLatestShipmentDate(Mage_Sales_Model_Order $order)
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

