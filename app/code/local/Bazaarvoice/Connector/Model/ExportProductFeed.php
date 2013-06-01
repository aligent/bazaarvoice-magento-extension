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
 * BazaarVoice product feed should be in the following format:
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
 *    TODO            <Brand>ProductBrand</Brand>
 *                <CategoryExternalId>1010</CategoryExternalId>
 *                <ProductPageUrl>http://www.site.com/product.htm?prod=2000001</ProductPageUrl>
 *                <ImageUrl>http://images.site.com/prodimages/2000001.gif</ImageUrl>
 *    TODO            <ManufacturerPartNumber>26-12345-8Z</ManufacturerPartNumber>
 *    TODO            <EAN>0213354752286</EAN>
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
     * process daily feed for the BazaarVoice. The feed will be FTPed to the BV FTP server
     *
     * Product & Catalog Feed to BV
     *
     */
    public function exportDailyProductFeed()
    {
        // Log
        Mage::log("Start Bazaarvoice product feed generation");
        // Iterate through all stores / views in this instance
        // (Not the 'admin' store view, which represents admin panel)
        $stores = Mage::app()->getStores(false);
        foreach ($stores as $store) {
            try {
                if (Mage::getStoreConfig('bazaarvoice/ProductFeed/EnableProductFeed', $store->getId()) === '1') {
                    Mage::log("    BV - Exporting product feed for store: " . $store->getCode(), Zend_Log::INFO);
                    $this->exportDailyProductFeedForStore($store);
                }
                else {
                    Mage::log("    BV - Product feed disabled for store: " . $store->getCode(), Zend_Log::INFO);
                }
            } catch (Exception $e) {
                Mage::log("    BV - Failed to export daily product feed for store: " . $store->getCode(), Zend_Log::ERR);
                Mage::log("    BV - Error message: " . $e->getMessage(), Zend_Log::ERR);
                Mage::logException($e);
                // Continue processing other stores
            }
        }
        // Log
        Mage::log("End Bazaarvoice product feed generation");
    }

    /**
     *
     * process daily feed for the BazaarVoice. The feed will be FTPed to the BV FTP server
     *
     * Product & Catalog Feed to BV
     *
     */
    public function exportDailyProductFeedForStore($store)
    {
        if (Mage::getStoreConfig("bazaarvoice/ProductFeed/EnableProductFeed", $store->getId()) === "1") {
            
            $productFeedFilePath = Mage::getBaseDir("var") . DS . 'export' . DS . 'bvfeeds';
            $productFeedFileName = 'productFeed-' . $store->getCode() . '-' . date('U') . '.xml';

            $ioObject = new Varien_Io_File();
            try {
                $ioObject->open(array('path'=>$productFeedFilePath));
            } catch (Exception $e) {
                $ioObject->mkdir($productFeedFilePath, 0777, true);
                $ioObject->open(array('path'=>$productFeedFilePath));
            }


            if ($ioObject->streamOpen($productFeedFileName)) {

                $ioObject->streamWrite("<?xml version=\"1.0\" encoding=\"UTF-8\"?>".
                    "<Feed xmlns=\"http://www.bazaarvoice.com/xs/PRR/ProductFeed/5.2\"".
                    " generator=\"Magento Extension r" . Mage::helper('bazaarvoice')->getExtensionVersion() . "\"".
                    "  name=\"".Mage::getStoreConfig("bazaarvoice/General/CustomerName", $store->getId())."\"".
                    "  incremental=\"false\"".
                    "  extractDate=\"".date('Y-m-d')."T".date('H:i:s').".000000\">\n");


                Mage::log("    BV - processing all categories");
                $this->processCategories($ioObject, $store);
                Mage::log("    BV - completed categories, beginning products");
                $this->processProducts($ioObject, $store);
                Mage::log("    BV - completed processing all products");

                $ioObject->streamWrite("</Feed>\n");
                $ioObject->streamClose();

                $destinationFile = 
                    "/" . Mage::getStoreConfig("bazaarvoice/ProductFeed/ExportPath", $store->getId()) . 
                    "/" . Mage::getStoreConfig("bazaarvoice/ProductFeed/ExportFileName", $store->getId());
                $sourceFile = $productFeedFilePath . DS . $productFeedFileName;
                $upload = Mage::helper('bazaarvoice')->uploadFile($sourceFile, $destinationFile, $store);

                if (!$upload) {
                    Mage::log("    Bazaarvoice FTP upload failed! [filename = " . $productFeedFileName . "]");
                } else {
                    Mage::log("    Bazaarvoice FTP upload success! [filename = " . $productFeedFileName . "]");
                    $ioObject->rm($productFeedFileName);
                }
            }
        }
    }

    private function processCategories($ioObject, $store)
    {
        // Lookup category path for root category
        // Assume only 1 store per website
        $rootCategoryId = $store->getRootCategoryId();
        $rootCategory = Mage::getModel('catalog/category')->load($rootCategoryId);
        $rootCategoryPath = $rootCategory->getPath();
        // Get category collection
        $categoryModel = Mage::getModel('catalog/category');
        $categoryIds = $categoryModel->getCollection();
        // Filter category collection based on Magento store
        // Do this by filtering on 'path' attribute, based on root category path found above
        // Include the root category itself in the feed
        $categoryIds
            ->addAttributeToFilter('path', array('like' => $rootCategoryPath . '%') );        
        // Check count of categories
        if (count($categoryIds) > 0) {
            $ioObject->streamWrite("<Categories>\n");
        }
        foreach ($categoryIds as $categoryId) {
            // Load category object
            $category = $categoryModel->load($categoryId->getId());
            $categoryExternalId = Mage::helper('bazaarvoice')->getCategoryId($category);
            $categoryName = htmlspecialchars($category->getName(), ENT_QUOTES, "UTF-8");
            $categoryPageUrl = htmlspecialchars($category->getCategoryIdUrl(), ENT_QUOTES, "UTF-8");

            if (!$category->getIsActive() || empty($categoryExternalId) || is_null($categoryExternalId) || $category->getLevel() == 1) {
                Mage::log("        BV - Skipping category: " . $category->getUrlKey());
                continue;
            }

            $parentExtId = "";
            $parentCategory = Mage::getModel('catalog/category')->load($categoryId->getParentId());
            // If parent category is the root category, then ignore it
            if (!is_null($parentCategory) && $parentCategory->getLevel() != 1) {
                $parentExtId = "    <ParentExternalId>" . Mage::helper('bazaarvoice')->getCategoryId($parentCategory) . "</ParentExternalId>\n";
            }
            
            array_push($this->_categoryIdList, $categoryExternalId);

            $ioObject->streamWrite("<Category>\n".
                "    <ExternalId>".$categoryExternalId."</ExternalId>\n".
                $parentExtId .
                "    <Name>".$categoryName."</Name>\n".
                "    <CategoryPageUrl>".$categoryPageUrl."</CategoryPageUrl>\n".
                "</Category>\n");
            
        }
        
        if (count($categoryIds) > 0) {
            $ioObject->streamWrite("</Categories>\n");
        }
    }

    private function processProducts($ioObject, $store)
    {
        // Category model instance
        $categoryModel = Mage::getModel('catalog/category');
        // Getting product model for access to product related functions
        $product = Mage::getModel('catalog/product');
            
        // *FROM MEMORY*  this should get all the products
        $productIds = $product->getCollection();
        // Filter collection for the specific store
        $productIds->addStoreFilter($store);
        // Filter collection for product status
        $productIds->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED);
            
        // Output tag only if more than 1 product
        if (count($productIds) > 0) {
            $ioObject->streamWrite("<Products>\n");
        }
        foreach ($productIds as $productId) {
            // Reset product model to prevent model data persisting between loop iterations.
            $product->reset();
            // Set store id before load, to get attribs for this particular store / view
            $product->setStoreId($store->getId());
            // Load product object
            $product->load($productId->getId());
            $productExternalId = Mage::helper('bazaarvoice')->getProductId($product);
            $brand = htmlspecialchars($product->getAttributeText("manufacturer"));


            // A status of 1 means enabled, 0 means disabled
            if ($product->getStatus() != 1 || empty($productExternalId) || is_null($productExternalId)) {
                Mage::log("        BV - Skipping product: " . $product->getSku());
                continue;
            }


            $ioObject->streamWrite("<Product>\n".
                "    <ExternalId>".$productExternalId."</ExternalId>\n".
                "    <Name>".htmlspecialchars($product->getName(), ENT_QUOTES, "UTF-8")."</Name>\n".
                "    <Description>".htmlspecialchars($product->getShortDescription(), ENT_QUOTES, "UTF-8")."</Description>\n");

            if (!is_null($brand) && !empty($brand)) {
                $ioObject->streamWrite("    <Brand><ExternalId>" . $brand . "</ExternalId></Brand>\n");
            }
                
            /* Make sure that CategoryExternalId is one written to Category section */
            $parentCategories = $productId->getCategoryIds();
            if (!is_null($parentCategories) && count($parentCategories) > 0) {
                foreach ($parentCategories as $parentCategoryId) {
                    $parentCategory = Mage::getModel("catalog/category")->load($parentCategoryId);
                    if ($parentCategory != null) {
                        $categoryExternalId = Mage::helper('bazaarvoice')->getCategoryId($parentCategory);
                        if (in_array($categoryExternalId, $this->_categoryIdList)) {
                            $ioObject->streamWrite("    <CategoryExternalId>" . $categoryExternalId . "</CategoryExternalId>\n");
                            break;
                        }
                    }
                }                
            }
            
            $ioObject->streamWrite("    <ProductPageUrl>".$product->getProductUrl()."</ProductPageUrl>\n".
                "    <ImageUrl>".$product->getImageUrl()."</ImageUrl>\n".
                "</Product>\n");
        }
        if (count($productIds) > 0) {
            $ioObject->streamWrite("</Products>\n");
        }
    }

}
