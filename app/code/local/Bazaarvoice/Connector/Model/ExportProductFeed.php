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
 * <Feed xmlns="http://www.bazaarvoice.com/xs/PRR/ProductFeed/3.3"
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

    private $_categoryIdList = array();

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
        Mage::log('Start Bazaarvoice product feed generation');
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
        Mage::log('End Bazaarvoice product feed generation');
    }

    /**
     *
     */
    private function exportDailyProductFeedByWebsite()
    {
        // Log
        Mage::log('Exporting product feed file for each website...');
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
                        Mage::log('    BV - Exporting product feed for website: ' . $website->getName(),
                            Zend_Log::INFO);
                        $this->exportDailyProductFeedForWebsite($website);
                    }
                    else {
                        Mage::throwException('No stores for website: ' . $website->getName());
                    }
                }
                else {
                    Mage::log('    BV - Product feed disabled for website: ' . $website->getName(), Zend_Log::INFO);
                }
            }
            catch (Exception $e) {
                Mage::log('    BV - Failed to export daily product feed for website: ' . $website->getName(),
                    Zend_Log::ERR);
                Mage::log('    BV - Error message: ' . $e->getMessage(), Zend_Log::ERR);
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
        Mage::log('Exporting product feed file for each store group...');
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
                        Mage::log('    BV - Exporting product feed for store group: ' . $group->getName(), Zend_Log::INFO);
                        $this->exportDailyProductFeedForStoreGroup($group);
                    }
                    else {
                        Mage::throwException('No stores for store group: ' . $group->getName());
                    }
                }
                else {
                    Mage::log('    BV - Product feed disabled for store group: ' . $group->getName(), Zend_Log::INFO);
                }
            }
            catch (Exception $e) {
                Mage::log('    BV - Failed to export daily product feed for store group: ' . $group->getName(),
                    Zend_Log::ERR);
                Mage::log('    BV - Error message: ' . $e->getMessage(), Zend_Log::ERR);
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
        Mage::log('Exporting product feed file for each store / store view...');
        // Iterate through all stores / groups in this instance
        // (Not the 'admin' store view, which represents admin panel)
        $stores = Mage::app()->getStores(false);
        /** @var $store Mage_Core_Model_Store */
        foreach ($stores as $store) {
            try {
                if (Mage::getStoreConfig('bazaarvoice/feeds/enable_product_feed', $store->getId()) === '1'
                    && Mage::getStoreConfig('bazaarvoice/general/enable_bv', $store->getId()) === '1'
                ) {
                    Mage::log('    BV - Exporting product feed for store: ' . $store->getCode(), Zend_Log::INFO);
                    $this->exportDailyProductFeedForStore($store);
                }
                else {
                    Mage::log('    BV - Product feed disabled for store: ' . $store->getCode(), Zend_Log::INFO);
                }
            }
            catch (Exception $e) {
                Mage::log('    BV - Failed to export daily product feed for store: ' . $store->getCode(), Zend_Log::ERR);
                Mage::log('    BV - Error message: ' . $e->getMessage(), Zend_Log::ERR);
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
        // Build local file name / path
        $productFeedFilePath = Mage::getBaseDir('var') . DS . 'export' . DS . 'bvfeeds';
        $productFeedFileName =
            $productFeedFilePath . DS . 'productFeed-website-' . $website->getId() . '-' . date('U') . '.xml';
        // Get client name for the scope
        $clientName = Mage::getStoreConfig('bazaarvoice/general/client_name', $website->getDefaultGroup()->getDefaultStoreId());

        // Create varien io object and write local feed file
        /* @var $ioObject Varien_Io_File */
        $ioObject = $this->createAndStartWritingFile($productFeedFileName, $clientName);
        Mage::log('    BV - processing all categories');
        $this->processCategoriesForWebsite($ioObject, $website);
        Mage::log('    BV - completed categories, beginning products');
        $this->processProductsForWebsite($ioObject, $website);
        Mage::log('    BV - completed processing all products');
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
        // Build local file name / path
        $productFeedFilePath = Mage::getBaseDir('var') . DS . 'export' . DS . 'bvfeeds';
        $productFeedFileName =
            $productFeedFilePath . DS . 'productFeed-group-' . $group->getId() . '-' . date('U') . '.xml';
        // Get client name for the scope
        $clientName = Mage::getStoreConfig('bazaarvoice/general/client_name', $group->getDefaultStoreId());

        // Create varien io object and write local feed file
        /* @var $ioObject Varien_Io_File */
        $ioObject = $this->createAndStartWritingFile($productFeedFileName, $clientName);
        Mage::log('    BV - processing all categories');
        $this->processCategoriesForGroup($ioObject, $group);
        Mage::log('    BV - completed categories, beginning products');
        $this->processProductsForGroup($ioObject, $group);
        Mage::log('    BV - completed processing all products');
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
        // Build local file name / path
        $productFeedFilePath = Mage::getBaseDir('var') . DS . 'export' . DS . 'bvfeeds';
        $productFeedFileName =
            $productFeedFilePath . DS . 'productFeed-store-' . $store->getId() . '-' . date('U') . '.xml';
        // Get client name for the scope
        $clientName = Mage::getStoreConfig('bazaarvoice/general/client_name', $store->getId());

        // Create varien io object and write local feed file
        /* @var $ioObject Varien_Io_File */
        $ioObject = $this->createAndStartWritingFile($productFeedFileName, $clientName);
        Mage::log('    BV - processing all categories');
        $this->processCategoriesForStore($ioObject, $store);
        Mage::log('    BV - completed categories, beginning products');
        $this->processProductsForStore($ioObject, $store);
        Mage::log('    BV - completed processing all products');
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
            Mage::log('    Bazaarvoice FTP upload failed! [filename = ' . $productFeedFileName . ']');
        }
        else {
            Mage::log('    Bazaarvoice FTP upload success! [filename = ' . $productFeedFileName . ']');
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
            Mage::throwException('Failed to open local feed file for writing: ' . $productFeedFileName);
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

    /**
     * @param Varien_Io_File $ioObject File object for feed file
     * @param Mage_Core_Model_Website $website
     */
    private function processCategoriesForWebsite(Varien_Io_File $ioObject, Mage_Core_Model_Website $website)
    {
        // Lookup category path for root category for default group in this website
        // NOTE:    This means we are only sending the category tree from the default group if there are multiple groups
        //          with different category trees...  In that case, admin must configure feed to be generated at group level
        $rootCategoryId = $website->getDefaultGroup()->getRootCategoryId();
        /* @var $rootCategory Mage_Catalog_Model_Category */
        $rootCategory = Mage::getModel('catalog/category')->load($rootCategoryId);
        $rootCategoryPath = $rootCategory->getData('path');
        // Get category collection
        $categoryIds = Mage::getModel('catalog/category')->getCollection();
        // Filter category collection based on Magento store
        // Do this by filtering on 'path' attribute, based on root category path found above
        // Include the root category itself in the feed
        $categoryIds
            ->addAttributeToFilter('level', array('gt' => 1))
            ->addAttributeToFilter('is_active', 1)
            ->addAttributeToFilter('path', array('like' => $rootCategoryPath . '%'));
        // Check count of categories
        if (count($categoryIds) > 0) {
            $ioObject->streamWrite("<Categories>\n");
        }
        /* @var $categoryId Mage_Catalog_Model_Category */
        foreach ($categoryIds as $categoryId) {
            // Load version of cat for all store views
            $categoriesByLocale = array();
            $categoryDefault = null;
            /* @var $store Mage_Core_Model_Store */
            foreach ($website->getStores() as $store) {
                /* @var $category Mage_Catalog_Model_Category */
                // Get new category model
                $category = Mage::getModel('catalog/category');
                // Set store id before load, to get attribs for this particular store / view
                $category->setStoreId($store->getId());
                // Load category object
                $category->load($categoryId->getId());
                // Set default category
                if ($website->getDefaultGroup()->getDefaultStoreId() == $store->getId()) {
                    $categoryDefault = $category;
                }
                // Get store locale
                $localeCode = Mage::getStoreConfig('bazaarvoice/general/locale', $store->getId());
                // Check localeCode
                if (!strlen($localeCode)) {
                    Mage::throwException('Invalid locale code (' . $localeCode . ') configured for store: ' .
                    $store->getCode());
                }
                // Add product to array
                $categoriesByLocale[$localeCode] = $category;
            }

            $this->writeCategory($ioObject, $categoryDefault, $categoriesByLocale);

        }

        if (count($categoryIds) > 0) {
            $ioObject->streamWrite("</Categories>\n");
        }
    }

    /**
     * @param Varien_Io_File $ioObject File object for feed file
     * @param Mage_Core_Model_Store_Group $group
     */
    private function processCategoriesForGroup(Varien_Io_File $ioObject, Mage_Core_Model_Store_Group $group)
    {
        // Lookup category path for root category
        $rootCategoryId = $group->getRootCategoryId();
        /* @var $rootCategory Mage_Catalog_Model_Category */
        $rootCategory = Mage::getModel('catalog/category')->load($rootCategoryId);
        $rootCategoryPath = $rootCategory->getData('path');
        // Get category collection
        $categoryIds = Mage::getModel('catalog/category')->getCollection();
        // Filter category collection based on Magento store
        // Do this by filtering on 'path' attribute, based on root category path found above
        // Include the root category itself in the feed
        $categoryIds
            ->addAttributeToFilter('level', array('gt' => 1))
            ->addAttributeToFilter('is_active', 1)
            ->addAttributeToFilter('path', array('like' => $rootCategoryPath . '%'));
        // Check count of categories
        if (count($categoryIds) > 0) {
            $ioObject->streamWrite("<Categories>\n");
        }
        /* @var $categoryId Mage_Catalog_Model_Category */
        foreach ($categoryIds as $categoryId) {
            // Load version of cat for all store views
            $categoriesByLocale = array();
            $categoryDefault = null;
            /* @var $store Mage_Core_Model_Store */
            foreach ($group->getStores() as $store) {
                /* @var $category Mage_Catalog_Model_Category */
                // Get new category model
                $category = Mage::getModel('catalog/category');
                // Set store id before load, to get attribs for this particular store / view
                $category->setStoreId($store->getId());
                // Load category object
                $category->load($categoryId->getId());
                // Set default category
                if ($group->getDefaultStoreId() == $store->getId()) {
                    $categoryDefault = $category;
                }
                // Get store locale
                $localeCode = Mage::getStoreConfig('bazaarvoice/general/locale', $store->getId());
                // Check localeCode
                if (!strlen($localeCode)) {
                    Mage::throwException('Invalid locale code (' . $localeCode . ') configured for store: ' .
                    $store->getCode());
                }
                // Add product to array
                $categoriesByLocale[$localeCode] = $category;
            }

            $this->writeCategory($ioObject, $categoryDefault, $categoriesByLocale);

        }

        if (count($categoryIds) > 0) {
            $ioObject->streamWrite("</Categories>\n");
        }
    }

    /**
     * @param Varien_Io_File $ioObject File object for feed file
     * @param Mage_Core_Model_Store $store
     */
    private function processCategoriesForStore(Varien_Io_File $ioObject, Mage_Core_Model_Store $store)
    {
        // Lookup category path for root category
        $rootCategoryId = $store->getRootCategoryId();
        /* @var $rootCategory Mage_Catalog_Model_Category */
        $rootCategory = Mage::getModel('catalog/category')->load($rootCategoryId);
        $rootCategoryPath = $rootCategory->getData('path');
        // Get category collection
        $categoryIds = Mage::getModel('catalog/category')->getCollection();
        // Filter category collection based on Magento store
        // Do this by filtering on 'path' attribute, based on root category path found above
        // Include the root category itself in the feed
        $categoryIds
            ->addAttributeToFilter('level', array('gt' => 1))
            ->addAttributeToFilter('is_active', 1)
            ->addAttributeToFilter('path', array('like' => $rootCategoryPath . '%'));
        // Check count of categories
        if (count($categoryIds) > 0) {
            $ioObject->streamWrite("<Categories>\n");
        }
        /* @var $categoryId Mage_Catalog_Model_Category */
        foreach ($categoryIds as $categoryId) {
            // Load version of cat for all store views
            $categoriesByLocale = array();
            // Setup parameters for writeCategory, using just this $store
            /* @var $categoryDefault Mage_Catalog_Model_Category */
            // Get new category model
            $categoryDefault = Mage::getModel('catalog/category');
            // Set store id before load, to get attributes for this particular store / view
            $categoryDefault->setStoreId($store->getId());
            // Load category object
            $categoryDefault->load($categoryId->getId());
            // Get store locale
            $localeCode = Mage::getStoreConfig('bazaarvoice/general/locale', $store->getId());
            // Build array of category by locale
            $categoriesByLocale[$localeCode] = $categoryDefault;
            // Write category to file
            $this->writeCategory($ioObject, $categoryDefault, $categoriesByLocale);

        }

        if (count($categoryIds) > 0) {
            $ioObject->streamWrite("</Categories>\n");
        }
    }

    /**
     * @param Varien_Io_File $ioObject File object for feed file
     * @param Mage_Catalog_Model_Category $categoryDefault
     * @param array $categoriesByLocale
     */
    private function writeCategory(Varien_Io_File $ioObject, Mage_Catalog_Model_Category $categoryDefault, array $categoriesByLocale)
    {
        // Get ref to BV helper
        /* @var $bvHelper Bazaarvoice_Connector_Helper_Data */
        $bvHelper = Mage::helper('bazaarvoice');

        // Get external id
        $categoryExternalId = $bvHelper->getCategoryId($categoryDefault, $categoryDefault->getStoreId());

        $categoryName = htmlspecialchars($categoryDefault->getName(), ENT_QUOTES, 'UTF-8');
        $categoryPageUrl = htmlspecialchars($categoryDefault->getCategoryIdUrl(), ENT_QUOTES, 'UTF-8');

        $parentExtId = '';
        /* @var $parentCategory Mage_Catalog_Model_Category */
        $parentCategory = Mage::getModel('catalog/category')->load($categoryDefault->getParentId());
        // If parent category is the root category, then ignore it
        if (!is_null($parentCategory) && $parentCategory->getLevel() != 1) {
            $parentExtId = '    <ParentExternalId>' .
                $bvHelper->getCategoryId($parentCategory, $categoryDefault->getStoreId()) . "</ParentExternalId>\n";
        }

        array_push($this->_categoryIdList, $categoryExternalId);

        $ioObject->streamWrite("<Category>\n" .
        "    <ExternalId>" . $categoryExternalId . "</ExternalId>\n" .
        $parentExtId .
        "    <Name><![CDATA[" . $categoryName . "]]></Name>\n" .
        "    <CategoryPageUrl><![CDATA[" . $categoryPageUrl . "]]></CategoryPageUrl>\n");

        // Write out localized <Names>
        $ioObject->streamWrite("    <Names>\n");
        /* @var $curCategory Mage_Catalog_Model_Category */
        foreach ($categoriesByLocale as $curLocale => $curCategory) {
            $ioObject->streamWrite('        <Name locale="' . $curLocale . '"><![CDATA[' .
            htmlspecialchars($curCategory->getName(), ENT_QUOTES, 'UTF-8') . "]]></Name>\n");
        }
        $ioObject->streamWrite("    </Names>\n");
        // Write out localized <CategoryPageUrls>
        $ioObject->streamWrite("    <CategoryPageUrls>\n");
        /* @var $curCategory Mage_Catalog_Model_Category */
        foreach ($categoriesByLocale as $curLocale => $curCategory) {
            $ioObject->streamWrite('        <CategoryPageUrl locale="' . $curLocale . '">' . "<![CDATA[" .
            htmlspecialchars($curCategory->getCategoryIdUrl(), ENT_QUOTES, 'UTF-8') . "]]>" . "</CategoryPageUrl>\n");
        }
        $ioObject->streamWrite("    </CategoryPageUrls>\n");

        $ioObject->streamWrite("</Category>\n");

    }

    /**
     *
     * @param Varien_Io_File $ioObject File object for feed file
     * @param Mage_Core_Model_Website $website
     */
    private function processProductsForWebsite(Varien_Io_File $ioObject, Mage_Core_Model_Website $website)
    {
        // *FROM MEMORY*  this should get all the products
        $productIds = Mage::getModel('catalog/product')->getCollection();
        // Filter collection for the specific website
        $productIds->addWebsiteFilter($website->getId());
        // Filter collection for product status
        $productIds->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED);
        // Filter collection for product visibility
        $productIds->addAttributeToFilter('visibility', array('neq' => Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE));

        // Output tag only if more than 1 product
        if (count($productIds) > 0) {
            $ioObject->streamWrite("<Products>\n");
        }
        /* @var $productId Mage_Catalog_Model_Product */
        foreach ($productIds as $productId) {
            // Load version of product for all store views
            $productsByLocale = array();
            /* @var $productDefault Mage_Catalog_Model_Product */
            $productDefault = null;
            /* @var $store Mage_Core_Model_Store */
            foreach ($website->getStores() as $store) {
                /* @var $product Mage_Catalog_Model_Product */
                // Get new product model
                $product = Mage::getModel('catalog/product');
                // Set store id before load, to get attribs for this particular store / view
                $product->setStoreId($store->getId());
                // Load product object
                $product->load($productId->getId());
                // Set bazaarvoice specific attributes
                $brand =
                    htmlspecialchars($product->getAttributeText(Mage::getStoreConfig('bazaarvoice/bv_config/product_feed_brand_attribute_code',
                        $store->getId())));
                $product->setData('brand', $brand);
                // Set default product
                if ($website->getDefaultGroup()->getDefaultStoreId() == $store->getId()) {
                    $productDefault = $product;
                }
                // Get store locale
                $localeCode = Mage::getStoreConfig('bazaarvoice/general/locale', $store->getId());
                // Check localeCode
                if (!strlen($localeCode)) {
                    Mage::throwException('Invalid locale code (' . $localeCode . ') configured for store: ' .
                    $store->getCode());
                }
                // Add product to array
                $productsByLocale[$localeCode] = $product;
            }

            // Write out individual product
            $this->writeProduct($ioObject, $productDefault, $productsByLocale);

        }
        if (count($productIds) > 0) {
            $ioObject->streamWrite("</Products>\n");
        }
    }

    /**
     *
     * @param Varien_Io_File $ioObject File object for feed file
     * @param Mage_Core_Model_Store_Group $group Store Group
     */
    private function processProductsForGroup(Varien_Io_File $ioObject, Mage_Core_Model_Store_Group $group)
    {
        // *FROM MEMORY*  this should get all the products
        $productIds = Mage::getModel('catalog/product')->getCollection();
        // Filter collection for the specific website
        $productIds->addWebsiteFilter($group->getWebsiteId());
        // Filter collection for product status
        $productIds->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED);
        // Filter collection for product visibility
        $productIds->addAttributeToFilter('visibility',
            array('neq' => Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE));

        // Output tag only if more than 1 product
        if (count($productIds) > 0) {
            $ioObject->streamWrite("<Products>\n");
        }
        /* @var $productId Mage_Catalog_Model_Product */
        foreach ($productIds as $productId) {
            // Load version of product for all store views
            $productsByLocale = array();
            /* @var $productDefault Mage_Catalog_Model_Product */
            $productDefault = null;
            /* @var $store Mage_Core_Model_Store */
            foreach ($group->getStores() as $store) {
                /* @var $product Mage_Catalog_Model_Product */
                // Get new product model
                $product = Mage::getModel('catalog/product');
                // Set store id before load, to get attributes for this particular store / view
                $product->setStoreId($store->getId());
                // Load product object
                $product->load($productId->getId());
                // Set bazaarvoice specific attributes
                $brand =
                    htmlspecialchars($product->getAttributeText(Mage::getStoreConfig('bazaarvoice/bv_config/product_feed_brand_attribute_code',
                        $store->getId())));
                $product->setData('brand', $brand);
                // Set default product
                if ($group->getDefaultStoreId() == $store->getId()) {
                    $productDefault = $product;
                }
                // Get store locale
                $localeCode = Mage::getStoreConfig('bazaarvoice/general/locale', $store->getId());
                // Check localeCode
                if (!strlen($localeCode)) {
                    Mage::throwException('Invalid locale code (' . $localeCode . ') configured for store: ' .
                    $store->getCode());
                }
                // Add product to array
                $productsByLocale[$localeCode] = $product;
            }

            // Write out individual product
            $this->writeProduct($ioObject, $productDefault, $productsByLocale);

        }
        if (count($productIds) > 0) {
            $ioObject->streamWrite("</Products>\n");
        }
    }

    /**
     * @param Varien_Io_File $ioObject File object for feed file
     * @param Mage_Core_Model_Store $store
     */
    private function processProductsForStore(Varien_Io_File $ioObject, Mage_Core_Model_Store $store)
    {
        // *FROM MEMORY*  this should get all the products
        $productIds = Mage::getModel('catalog/product')->getCollection();
        // Filter collection for the specific website
        $productIds->addWebsiteFilter($store->getWebsiteId());
        // Filter collection for product status
        $productIds->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED);
        // Filter collection for product visibility
        $productIds->addAttributeToFilter('visibility',
            array('neq' => Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE));

        // Output tag only if more than 1 product
        if (count($productIds) > 0) {
            $ioObject->streamWrite("<Products>\n");
        }
        /* @var $productId Mage_Catalog_Model_Product */
        foreach ($productIds as $productId) {
            // Load version of product for all store views
            $productsByLocale = array();
            /* @var $productDefault Mage_Catalog_Model_Product */
            $productDefault = Mage::getModel('catalog/product');
            // Set store id before load, to get attributes for this particular store / view
            $productDefault->setStoreId($store->getId());
            // Load product object
            $productDefault->load($productId->getId());
            // Set bazaarvoice specific attributes
            $brand =
                htmlspecialchars($productDefault->getAttributeText(Mage::getStoreConfig('bazaarvoice/bv_config/product_feed_brand_attribute_code',
                    $store->getId())));
            $productDefault->setData('brand', $brand);
            // Get store locale
            $localeCode = Mage::getStoreConfig('bazaarvoice/general/locale', $store->getId());
            // Check localeCode
            if (!strlen($localeCode)) {
                Mage::throwException('Invalid locale code (' . $localeCode . ') configured for store: ' .
                $store->getCode());
            }
            // Add product to array
            $productsByLocale[$localeCode] = $productDefault;
            // Write out individual product
            $this->writeProduct($ioObject, $productDefault, $productsByLocale);
        }
        if (count($productIds) > 0) {
            $ioObject->streamWrite("</Products>\n");
        }
    }

    /**
     * @param Varien_Io_File $ioObject File object for feed file
     * @param Mage_Catalog_Model_Product $productDefault
     * @param array $productsByLocale
     */
    private function writeProduct(Varien_Io_File $ioObject, Mage_Catalog_Model_Product $productDefault,
                                  array $productsByLocale)
    {
        // Get ref to BV helper
        /* @var $bvHelper Bazaarvoice_Connector_Helper_Data */
        $bvHelper = Mage::helper('bazaarvoice');

        // Generate product external ID from SKU, this is the same for all groups / stores / views
        $productExternalId = $bvHelper->getProductId($productDefault);

        $ioObject->streamWrite("<Product>\n" .
        '    <ExternalId>' . $productExternalId . "</ExternalId>\n" .
        '    <Name><![CDATA[' . htmlspecialchars($productDefault->getName(), ENT_QUOTES, 'UTF-8') . "]]></Name>\n" .
        '    <Description><![CDATA[' . htmlspecialchars($productDefault->getData('short_description'), ENT_QUOTES, 'UTF-8') .
        "]]></Description>\n");

        $brandId = $bvHelper->getBrandId($productDefault);
        if (!is_null($brandId) && !empty($brandId)) {
            $ioObject->streamWrite('    <Brand><ExternalId>' . $brandId . "</ExternalId></Brand>\n");
        }

        /* Make sure that CategoryExternalId is one written to Category section */
        $parentCategories = $productDefault->getCategoryIds();
        if (!is_null($parentCategories) && count($parentCategories) > 0) {
            foreach ($parentCategories as $parentCategoryId) {
                $parentCategory = Mage::getModel('catalog/category')->load($parentCategoryId);
                if ($parentCategory != null) {
                    $categoryExternalId = $bvHelper->getCategoryId($parentCategory, $productDefault->getStoreId());
                    if (in_array($categoryExternalId, $this->_categoryIdList)) {
                        $ioObject->streamWrite('    <CategoryExternalId>' . $categoryExternalId .
                        "</CategoryExternalId>\n");
                        break;
                    }
                }
            }
        }

        $ioObject->streamWrite('    <ProductPageUrl>' . "<![CDATA[" . $productDefault->getProductUrl() . "]]>" . "</ProductPageUrl>\n");
        try {
            $imageUrl = $productDefault->getImageUrl();
            if (strlen($imageUrl)) {
                $ioObject->streamWrite('    <ImageUrl>' . "<![CDATA[" . $imageUrl . "]]>" . "</ImageUrl>\n");
            }
        }
        catch (Exception $e) {
            Mage::log('Failed to get image URL for product sku: ' . $productDefault->getSku());
            Mage::log('Continuing generating feed.');
        }

        // Write out localized <Names>
        $ioObject->streamWrite("    <Names>\n");
        foreach ($productsByLocale as $curLocale => $curProduct) {
            $ioObject->streamWrite('        <Name locale="' . $curLocale . '"><![CDATA[' .
            htmlspecialchars($productDefault->getData('name'), ENT_QUOTES, 'UTF-8') . "]]></Name>\n");
        }
        $ioObject->streamWrite("    </Names>\n");
        // Write out localized <Descriptions>
        $ioObject->streamWrite("    <Descriptions>\n");
        foreach ($productsByLocale as $curLocale => $curProduct) {
            $ioObject->streamWrite('         <Description locale="' . $curLocale . '"><![CDATA[' .
            htmlspecialchars($productDefault->getData('short_description'), ENT_QUOTES, 'UTF-8') . "]]></Description>\n");
        }
        $ioObject->streamWrite("    </Descriptions>\n");
        // Write out localized <ProductPageUrls>
        $ioObject->streamWrite("    <ProductPageUrls>\n");
        foreach ($productsByLocale as $curLocale => $curProduct) {
            $ioObject->streamWrite('        <ProductPageUrl locale="' . $curLocale . '">' . "<![CDATA[" .
            $productDefault->getProductUrl() . "]]>" . "</ProductPageUrl>\n");
        }
        $ioObject->streamWrite("    </ProductPageUrls>\n");
        // Write out localized <ImageUrls>
        $ioObject->streamWrite("    <ImageUrls>\n");
        foreach ($productsByLocale as $curLocale => $curProduct) {
            try {
                $imageUrl = $productDefault->getImageUrl();
                if (strlen($imageUrl)) {
                    $ioObject->streamWrite('        <ImageUrl locale="' . $curLocale . '">' . "<![CDATA[" . $imageUrl .
                    "]]>" . "</ImageUrl>\n");
                }
            }
            catch (Exception $e) {
                Mage::log('Failed to get image URL for product sku: ' . $productDefault->getSku());
                Mage::log('Continuing generating feed.');
            }
        }
        $ioObject->streamWrite("    </ImageUrls>\n");

        // Close this product
        $ioObject->streamWrite("</Product>\n");
    }

}
