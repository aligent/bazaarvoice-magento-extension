<?php
// @codingStandardsIgnoreStart
/**
 * Event observer and indexer running application
 *
 * @author Bazaarvoice, Inc.
 */
// @codingStandardsIgnoreEnd

/**
 *
 * Bazaarvoice product feed should be in the following format:
 *
 * <?xml version="1.0" encoding="UTF-8"?>
 * <Feed xmlns="http://www.bazaarvoice.com/xs/PRR/ProductFeed/5.2"
 *           name="SiteName"
 *           incremental="false"
 *          extractDate="2007-01-01T12:00:00.000000">
 *        <Categories>
 *            <Category>
 *                <ExternalId>1010</ExternalId>
 *                <Name>First Category</Name>
 *                <CategoryPageUrl>http://www.site.com/category.htm?cat=1010</CategoryPageUrl>
 *            </Category>
 *            ..... 0-n categories
 *        </Categories>
 *        <Products>
 *            <Product>
 *                <ExternalId>2000001</ExternalId>
 *                <Name>First Product</Name>
 *                <Description>First Product Description Text</Description>
 *                <Brand>ProductBrand</Brand>
 *                <CategoryExternalId>1010</CategoryExternalId>
 *                <ProductPageUrl>http://www.site.com/product.htm?prod=2000001</ProductPageUrl>
 *                <ImageUrl>http://images.site.com/prodimages/2000001.gif</ImageUrl>
 *                <ManufacturerPartNumber>26-12345-8Z</ManufacturerPartNumber>
 *                <EAN>0213354752286</EAN>
 *            </Product>
 *            ....... 0-n products
 *        </Products>
 *</Feed>
 */

/**
 * Product Feed Export Class
 */
class Bazaarvoice_Connector_Model_ExportProductFeed extends Mage_Core_Model_Abstract
{

    protected function _construct()
    {
    }

    /**
     *
     * process daily feed for the Bazaarvoice. The feed will be FTPed to the BV FTP server
     *
     * Product & Catalog Feed to BV
     *
     */
    public function exportDailyProductFeed()
    {
        // Log
        Mage::log('Start Bazaarvoice product feed generation', Zend_Log::DEBUG, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
        // Check global setting to see what at which scope / level we should generate feeds
        $feedGenScope = Mage::getStoreConfig('bazaarvoice/feeds/generation_scope');
        switch ($feedGenScope) {
            case Bazaarvoice_Connector_Model_Source_FeedGenerationScope::SCOPE_WEBSITE:
                $this->exportDailyProductFeedByWebsite();
                break;
            case Bazaarvoice_Connector_Model_Source_FeedGenerationScope::SCOPE_STORE_GROUP:
                $this->exportDailyProductFeedByGroup();
                break;
            case Bazaarvoice_Connector_Model_Source_FeedGenerationScope::SCOPE_STORE_VIEW:
                $this->exportDailyProductFeedByStore();
                break;
        }
        // Log
        Mage::log('End Bazaarvoice product feed generation', Zend_Log::DEBUG, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
    }

    /**
     *
     */
    private function exportDailyProductFeedByWebsite()
    {
        // Log
        Mage::log('Exporting product feed file for each website...', Zend_Log::DEBUG, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
        // Iterate through all websites in this instance
        // (Not the 'admin' store view, which represents admin panel)
        $websites = Mage::app()->getWebsites(false);
        /** @var $website Mage_Core_Model_Website */
        foreach ($websites as $website) {
            try {
                if (Mage::getStoreConfig('bazaarvoice/feeds/enable_product_feed', $website->getDefaultGroup()->getDefaultStoreId()) ===
                    '1'
                    && Mage::getStoreConfig('bazaarvoice/general/enable_bv', $website->getDefaultGroup()->getDefaultStoreId()) === '1'
                ) {
                    if (count($website->getStores()) > 0) {
                        Mage::log('    BV - Exporting product feed for website: ' . $website->getName(),                            Zend_Log::INFO, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
                        $this->exportDailyProductFeedForWebsite($website);
                    }
                    else {
                        Mage::throwException('No stores for website: ' . $website->getName());
                    }
                }
                else {
                    Mage::log('    BV - Product feed disabled for website: ' . $website->getName(), Zend_Log::INFO, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
                }
            }
            catch (Exception $e) {
                Mage::log('    BV - Failed to export daily product feed for website: ' . $website->getName(),                    Zend_Log::ERR, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
                Mage::log('    BV - Error message: ' . $e->getMessage(), Zend_Log::ERR, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
                Mage::logException($e);
                // Continue processing other websites
            }
        }
    }

    /**
     *
     */
    public function exportDailyProductFeedByGroup()
    {
        // Log
        Mage::log('Exporting product feed file for each store group...', Zend_Log::DEBUG, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
        // Iterate through all stores / groups in this instance
        // (Not the 'admin' store view, which represents admin panel)
        $groups = Mage::app()->getGroups(false);
        /** @var $group Mage_Core_Model_Store_Group */
        foreach ($groups as $group) {
            try {
                if (Mage::getStoreConfig('bazaarvoice/feeds/enable_product_feed', $group->getDefaultStoreId()) === '1'
                    && Mage::getStoreConfig('bazaarvoice/general/enable_bv', $group->getDefaultStoreId()) === '1'
                ) {
                    if (count($group->getStores()) > 0) {
                        Mage::log('    BV - Exporting product feed for store group: ' . $group->getName(), Zend_Log::INFO, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
                        $this->exportDailyProductFeedForStoreGroup($group);
                    }
                    else {
                        Mage::throwException('No stores for store group: ' . $group->getName());
                    }
                }
                else {
                    Mage::log('    BV - Product feed disabled for store group: ' . $group->getName(), Zend_Log::INFO, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
                }
            }
            catch (Exception $e) {
                Mage::log('    BV - Failed to export daily product feed for store group: ' . $group->getName(),                    Zend_Log::ERR, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
                Mage::log('    BV - Error message: ' . $e->getMessage(), Zend_Log::ERR, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
                Mage::logException($e);
                // Continue processing other store groups
            }
        }
    }

    /**
     *
     */
    private function exportDailyProductFeedByStore()
    {
        // Log
        Mage::log('Exporting product feed file for each store / store view...', Zend_Log::DEBUG, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
        // Iterate through all stores / groups in this instance
        // (Not the 'admin' store view, which represents admin panel)
        $stores = Mage::app()->getStores(false);
        /** @var $store Mage_Core_Model_Store */
        foreach ($stores as $store) {
            try {
                if (Mage::getStoreConfig('bazaarvoice/feeds/enable_product_feed', $store->getId()) === '1'
                    && Mage::getStoreConfig('bazaarvoice/general/enable_bv', $store->getId()) === '1'
                ) {
                    Mage::log('    BV - Exporting product feed for store: ' . $store->getCode(), Zend_Log::INFO, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
                    $this->exportDailyProductFeedForStore($store);
                }
                else {
                    Mage::log('    BV - Product feed disabled for store: ' . $store->getCode(), Zend_Log::INFO, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
                }
            }
            catch (Exception $e) {
                Mage::log('    BV - Failed to export daily product feed for store: ' . $store->getCode(), Zend_Log::ERR, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
                Mage::log('    BV - Error message: ' . $e->getMessage(), Zend_Log::ERR, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
                Mage::logException($e);
                // Continue processing other store groups
            }
        }
    }

    /**
     * process daily feed for the Bazaarvoice. The feed will be FTPed to the BV FTP server
     *
     * Product & Catalog Feed to BV
     *
     * @param Mage_Core_Model_Website $website Website
     *
     */
    public function exportDailyProductFeedForWebsite(Mage_Core_Model_Website $website)
    {
        /** @var Bazaarvoice_Connector_Model_ProductFeed_Category $categoryModel */
        $categoryModel = Mage::getModel('bazaarvoice/productFeed_category');
        /** @var Bazaarvoice_Connector_Model_ProductFeed_Product $productModel */
        $productModel = Mage::getModel('bazaarvoice/productFeed_product');
        /** @var Bazaarvoice_Connector_Model_ProductFeed_Brand $brandModel */
        $brandModel = Mage::getModel('bazaarvoice/productFeed_brand');

        // Build local file name / path
        $productFeedFilePath = Mage::getBaseDir('var') . DS . 'export' . DS . 'bvfeeds';
        $productFeedFileName =
            $productFeedFilePath . DS . 'productFeed-website-' . $website->getId() . '-' . date('U') . '.xml';
        // Get client name for the scope
        $clientName = Mage::getStoreConfig('bazaarvoice/general/client_name', $website->getDefaultGroup()->getDefaultStoreId());

        // Create varien io object and write local feed file
        /* @var $ioObject Varien_Io_File */
        $ioObject = $this->createAndStartWritingFile($productFeedFileName, $clientName);
        Mage::log('    BV - processing all brands', Zend_Log::DEBUG, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
        $brandModel->processBrandsForWebsite($ioObject, $website);
        Mage::log('    BV - completed brands, beginning categories', Zend_Log::DEBUG, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
        $categoryModel->processCategoriesForWebsite($ioObject, $website);
        Mage::log('    BV - completed categories, beginning products', Zend_Log::DEBUG, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
        $productModel->setCategoryIdList($categoryModel->getCategoryIdList());
        $productModel->processProductsForWebsite($ioObject, $website);
        Mage::log('    BV - completed processing all products', Zend_Log::DEBUG, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
        $this->closeAndFinishWritingFile($ioObject);

        // Upload feed
        $this->uploadFeed($productFeedFileName, $website->getDefaultStore());

    }

    /**
     * process daily feed for the Bazaarvoice. The feed will be FTPed to the BV FTP server
     *
     * Product & Catalog Feed to BV
     *
     * @param Mage_Core_Model_Store_Group $group Store Group
     *
     */
    public function exportDailyProductFeedForStoreGroup(Mage_Core_Model_Store_Group $group)
    {
        /** @var Bazaarvoice_Connector_Model_ProductFeed_Category $categoryModel */
        $categoryModel = Mage::getModel('bazaarvoice/productFeed_category');
        /** @var Bazaarvoice_Connector_Model_ProductFeed_Product $productModel */
        $productModel = Mage::getModel('bazaarvoice/productFeed_product');
        /** @var Bazaarvoice_Connector_Model_ProductFeed_Brand $brandModel */
        $brandModel = Mage::getModel('bazaarvoice/productFeed_brand');

        // Build local file name / path
        $productFeedFilePath = Mage::getBaseDir('var') . DS . 'export' . DS . 'bvfeeds';
        $productFeedFileName =
            $productFeedFilePath . DS . 'productFeed-group-' . $group->getId() . '-' . date('U') . '.xml';
        // Get client name for the scope
        $clientName = Mage::getStoreConfig('bazaarvoice/general/client_name', $group->getDefaultStoreId());

        // Create varien io object and write local feed file
        /* @var $ioObject Varien_Io_File */
        $ioObject = $this->createAndStartWritingFile($productFeedFileName, $clientName);
        Mage::log('    BV - processing all brands', Zend_Log::DEBUG, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
        $brandModel->processBrandsForGroup($ioObject, $group);
        Mage::log('    BV - completed brands, beginning categories', Zend_Log::DEBUG, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
        $categoryModel->processCategoriesForGroup($ioObject, $group);
        Mage::log('    BV - completed categories, beginning products', Zend_Log::DEBUG, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
        $productModel->setCategoryIdList($categoryModel->getCategoryIdList());
        $productModel->processProductsForGroup($ioObject, $group);
        Mage::log('    BV - completed processing all products', Zend_Log::DEBUG, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
        $this->closeAndFinishWritingFile($ioObject);

        // Upload feed
        $this->uploadFeed($productFeedFileName, $group->getDefaultStore());

    }

    /**
     * process daily feed for the Bazaarvoice. The feed will be FTPed to the BV FTP server
     *
     * Product & Catalog Feed to BV
     * @param Mage_Core_Model_Store $store
     *
     */
    public function exportDailyProductFeedForStore(Mage_Core_Model_Store $store)
    {
        /** @var Bazaarvoice_Connector_Model_ProductFeed_Category $categoryModel */
        $categoryModel = Mage::getModel('bazaarvoice/productFeed_category');
        /** @var Bazaarvoice_Connector_Model_ProductFeed_Product $productModel */
        $productModel = Mage::getModel('bazaarvoice/productFeed_product');
        /** @var Bazaarvoice_Connector_Model_ProductFeed_Brand $brandModel */
        $brandModel = Mage::getModel('bazaarvoice/productFeed_brand');

        // Build local file name / path
        $productFeedFilePath = Mage::getBaseDir('var') . DS . 'export' . DS . 'bvfeeds';
        $productFeedFileName =
            $productFeedFilePath . DS . 'productFeed-store-' . $store->getId() . '-' . date('U') . '.xml';
        // Get client name for the scope
        $clientName = Mage::getStoreConfig('bazaarvoice/general/client_name', $store->getId());

        // Create varien io object and write local feed file
        /* @var $ioObject Varien_Io_File */
        $ioObject = $this->createAndStartWritingFile($productFeedFileName, $clientName);
        Mage::log('    BV - processing all brands', Zend_Log::DEBUG, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
        $brandModel->processBrandsForStore($ioObject, $store);
        Mage::log('    BV - completed brands, beginning categories', Zend_Log::DEBUG, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
        $categoryModel->processCategoriesForStore($ioObject, $store);
        Mage::log('    BV - completed categories, beginning products', Zend_Log::DEBUG, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
        $productModel->setCategoryIdList($categoryModel->getCategoryIdList());
        $productModel->processProductsForStore($ioObject, $store);
        Mage::log('    BV - completed processing all products', Zend_Log::DEBUG, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
        $this->closeAndFinishWritingFile($ioObject);

        // Upload feed
        $this->uploadFeed($productFeedFileName, $store);

    }

    /**
     * @param $productFeedFileName
     * @param Mage_Core_Model_Store $store
     */
    private function uploadFeed($productFeedFileName, Mage_Core_Model_Store $store)
    {
        // Get ref to BV helper
        /* @var $bvHelper Bazaarvoice_Connector_Helper_Data */
        $bvHelper = Mage::helper('bazaarvoice');

        // Get path and filename from custom config settings
        $destinationFile = Mage::getStoreConfig('bazaarvoice/bv_config/product_feed_export_export_path', $store->getId()) . '/' .
            Mage::getStoreConfig('bazaarvoice/bv_config/product_feed_export_filename', $store->getId());
        $sourceFile = $productFeedFileName;
        $upload = $bvHelper->uploadFile($sourceFile, $destinationFile, $store);

        if (!$upload) {
            Mage::log('    Bazaarvoice FTP upload failed! [filename = ' . $productFeedFileName . ']', Zend_Log::DEBUG, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
        }
        else {
            Mage::log('    Bazaarvoice FTP upload success! [filename = ' . $productFeedFileName . ']', Zend_Log::DEBUG, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
            $ioObject = new Varien_Io_File();
            $ioObject->rm($productFeedFileName);
        }
    }

    /**
     * @param string $productFeedFileName Name of local product feed file to create and write
     * @param string $clientName BV Client name text
     * @return Varien_Io_File File object, opening <Feed> tag is already written
     */
    private function createAndStartWritingFile($productFeedFileName, $clientName)
    {
        // Get ref to BV helper
        /* @var $bvHelper Bazaarvoice_Connector_Helper_Data */
        $bvHelper = Mage::helper('bazaarvoice');

        $ioObject = new Varien_Io_File();
        try {
            $ioObject->open(array('path' => dirname($productFeedFileName)));
        }
        catch (Exception $e) {
            $ioObject->mkdir(dirname($productFeedFileName), 0777, true);
            $ioObject->open(array('path' => dirname($productFeedFileName)));
        }

        if (!$ioObject->streamOpen(basename($productFeedFileName))) {
            Mage::throwException('Failed to open local feed file for writing: ' . $productFeedFileName, Zend_Log::DEBUG, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
        }

        $ioObject->streamWrite("<?xml version=\"1.0\" encoding=\"UTF-8\"?>" .
        "<Feed xmlns=\"http://www.bazaarvoice.com/xs/PRR/ProductFeed/5.2\"" .
        " generator=\"Magento Extension r" . $bvHelper->getExtensionVersion() . "\"" .
        "  name=\"" . $clientName . "\"" .
        "  incremental=\"false\"" .
        "  extractDate=\"" . date('Y-m-d') . "T" . date('H:i:s') . ".000000\">\n");

        return $ioObject;
    }

    /**
     * @param Varien_Io_File $ioObject File object for feed file
     */
    private function closeAndFinishWritingFile(Varien_Io_File $ioObject)
    {
        $ioObject->streamWrite("</Feed>\n");
        $ioObject->streamClose();
    }

}
