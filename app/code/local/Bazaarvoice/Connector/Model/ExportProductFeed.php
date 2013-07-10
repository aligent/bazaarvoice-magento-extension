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
 *                <Brand>ProductBrand</Brand>
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

    // Hard code export path and filename in class constants
    const EXPORT_PATH       = '/import-inbox';
    const EXPORT_FILENAME   = 'productfeed.xml';
    // Magento Brand Attrbute Code
    // The following attribute code will be used to locate the brand of products
    // which is sent in the product feed:
    const MAGE_BRAND_ATTRIBUTE  = 'manufacturer';

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
        Mage::log('Start Bazaarvoice product feed generation');
        // Iterate through all stores / groups in this instance
        // (Not the 'admin' store view, which represents admin panel)
        $groups = Mage::app()->getGroups(false);
        /** @var $group Mage_Core_Model_Store_Group */
        foreach ($groups as $group) {
            try {
                if (Mage::getStoreConfig('bazaarvoice/General/enable_product_feed', $group->getDefaultStoreId()) === '1'
                    && Mage::getStoreConfig('bazaarvoice/General/enable_bv', $group->getDefaultStoreId()) === '1') {
                    if(count($group->getStores()) > 0) {
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
            } catch (Exception $e) {
                Mage::log('    BV - Failed to export daily product feed for store group: ' . $group->getName(), Zend_Log::ERR);
                Mage::log('    BV - Error message: ' . $e->getMessage(), Zend_Log::ERR);
                Mage::logException($e);
                // Continue processing other store groups
            }
        }
        // Log
        Mage::log('End Bazaarvoice product feed generation');
    }

    /**
     *
     * process daily feed for the BazaarVoice. The feed will be FTPed to the BV FTP server
     *
     * Product & Catalog Feed to BV
     *
     * @param Mage_Core_Model_Store_Group $group Store Group
     *
     */
    public function exportDailyProductFeedForStoreGroup($group)
    {
        if (Mage::getStoreConfig('bazaarvoice/General/enable_product_feed', $group->getDefaultStoreId()) === '1') {
            
            $productFeedFilePath = Mage::getBaseDir('var') . DS . 'export' . DS . 'bvfeeds';
            $productFeedFileName = 'productFeed-' . $group->getGroupId() . '-' . date('U') . '.xml';

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
                    "  name=\"".Mage::getStoreConfig("bazaarvoice/General/client_name", $group->getDefaultStoreId())."\"".
                    "  incremental=\"false\"".
                    "  extractDate=\"".date('Y-m-d')."T".date('H:i:s').".000000\">\n");


                Mage::log('    BV - processing all categories');
                $this->processCategories($ioObject, $group);
                Mage::log('    BV - completed categories, beginning products');
                $this->processProducts($ioObject, $group);
                Mage::log('    BV - completed processing all products');

                $ioObject->streamWrite("</Feed>\n");
                $ioObject->streamClose();

                // Hard code path and filename in class constants
                $destinationFile = Bazaarvoice_Connector_Model_ExportProductFeed::EXPORT_PATH . '/' . 
                    Bazaarvoice_Connector_Model_ExportProductFeed::EXPORT_FILENAME;
                $sourceFile = $productFeedFilePath . DS . $productFeedFileName;
                $upload = Mage::helper('bazaarvoice')->uploadFile($sourceFile, $destinationFile, $group->getDefaultStore());

                if (!$upload) {
                    Mage::log('    Bazaarvoice FTP upload failed! [filename = ' . $productFeedFileName . ']');
                } else {
                    Mage::log('    Bazaarvoice FTP upload success! [filename = ' . $productFeedFileName . ']');
                    $ioObject->rm($productFeedFileName);
                }
            }
        }
    }

    /**
     *
     * @param Mage_Core_Model_Store_Group $group Store Group
     *
     */
    private function processCategories($ioObject, $group)
    {
        // Lookup category path for root category
        $rootCategoryId = $group->getRootCategoryId();
        $rootCategory = Mage::getModel('catalog/category')->load($rootCategoryId);
        $rootCategoryPath = $rootCategory->getPath();
        // Get category collection
        $categoryIds = Mage::getModel('catalog/category')->getCollection();
        // Filter category collection based on Magento store
        // Do this by filtering on 'path' attribute, based on root category path found above
        // Include the root category itself in the feed
        $categoryIds
            ->addAttributeToFilter('level', array('gt' => 1) )
            ->addAttributeToFilter('is_active', 1)
            ->addAttributeToFilter('path', array('like' => $rootCategoryPath . '%') );        
        // Check count of categories
        if (count($categoryIds) > 0) {
            $ioObject->streamWrite("<Categories>\n");
        }
        foreach ($categoryIds as $categoryId) {
            // Load version of cat for all store views
            $categoryViews = array();
            $categoryDefault = null;
            foreach($group->getStores() as $store) {                
                // Get new category model
                $category = Mage::getModel('catalog/category');
                // Set store id before load, to get attribs for this particular store / view
                $category->setStoreId($store->getId());
                // Load category object
                $category->load($categoryId->getId());
                // Set default category
                if($group->getDefaultStoreId() == $store->getStoreId()) {
                    $categoryDefault = $category;
                }
                // Get store locale
                $localeCode = Mage::getStoreConfig('general/locale/code', $store->getId());
                // Add product to array
                $categoryViews[$localeCode] = $category;
            }

            // Get external id
            $categoryExternalId = Mage::helper('bazaarvoice')->getCategoryId($categoryDefault);

            $categoryName = htmlspecialchars($categoryDefault->getName(), ENT_QUOTES, 'UTF-8');
            $categoryPageUrl = htmlspecialchars($categoryDefault->getCategoryIdUrl(), ENT_QUOTES, 'UTF-8');

            $parentExtId = '';
            $parentCategory = Mage::getModel('catalog/category')->load($categoryId->getParentId());
            // If parent category is the root category, then ignore it
            if (!is_null($parentCategory) && $parentCategory->getLevel() != 1) {
                $parentExtId = '    <ParentExternalId>' . Mage::helper('bazaarvoice')->getCategoryId($parentCategory) . "</ParentExternalId>\n";
            }
            
            array_push($this->_categoryIdList, $categoryExternalId);

            $ioObject->streamWrite("<Category>\n".
                "    <ExternalId>".$categoryExternalId."</ExternalId>\n".
                $parentExtId .
                "    <Name>".$categoryName."</Name>\n".
                "    <CategoryPageUrl>".$categoryPageUrl."</CategoryPageUrl>\n");
                
            // Write out localized <Names>
            $ioObject->streamWrite("    <Names>\n");
            foreach($categoryViews as $curLocale => $curCategory) {
                $ioObject->streamWrite('        <Name locale="' . $curLocale . '">' . htmlspecialchars($curCategory->getName(), ENT_QUOTES, 'UTF-8') . "</Name>\n");
            }
            $ioObject->streamWrite("    </Names>\n");
            // Write out localized <CategoryPageUrls>
            $ioObject->streamWrite("    <CategoryPageUrls>\n");
            foreach($categoryViews as $curLocale => $curCategory) {
                $ioObject->streamWrite('        <CategoryPageUrl locale="' . $curLocale . '">' . htmlspecialchars($curCategory->getCategoryIdUrl(), ENT_QUOTES, 'UTF-8') . "</CategoryPageUrl>\n");
            }
            $ioObject->streamWrite("    </CategoryPageUrls>\n");
            
            $ioObject->streamWrite("</Category>\n");
            
        }
        
        if (count($categoryIds) > 0) {
            $ioObject->streamWrite("</Categories>\n");
        }
    }

    /**
     *
     * @param Mage_Core_Model_Store_Group $group Store Group
     *
     */
    private function processProducts($ioObject, $group)
    {
        // Category model instance
        $categoryModel = Mage::getModel('catalog/category');
            
        // *FROM MEMORY*  this should get all the products
        $productIds =  Mage::getModel('catalog/product')->getCollection();
        // Filter collection for the specific website
        $productIds->addWebsiteFilter($group->getWebsiteId());
        // Filter collection for product status
        $productIds->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED);
        // Filter collection for product visibility
        $productIds->addAttributeToFilter('visibility', array('neq' => Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE));
            
        // Output tag only if more than 1 product
        if (count($productIds) > 0) {
            $ioObject->streamWrite("<Products>\n");
        }
        foreach ($productIds as $productId) {
            // Load version of product for all store views
            $productViews = array();
            $productDefault = null;
            foreach($group->getStores() as $store) {                
                // Get new product model
                $product = Mage::getModel('catalog/product');
                // Set store id before load, to get attribs for this particular store / view
                $product->setStoreId($store->getId());
                // Load product object
                $product->load($productId->getId());
                // Set bazaarvoice specific attributes
                $brand = htmlspecialchars($product->getAttributeText(Bazaarvoice_Connector_Model_ExportProductFeed::MAGE_BRAND_ATTRIBUTE));
                $product->setBrand($brand);
                // Set default product
                if($group->getDefaultStoreId() == $store->getStoreId()) {
                    $productDefault = $product;
                }
                // Get store locale
                $localeCode = Mage::getStoreConfig('general/locale/code', $store->getId());
                // Add product to array
                $productViews[$localeCode] = $product;
            }

            // Generate product external ID from SKU, this is the same for all groups / stores / views
            $productExternalId = Mage::helper('bazaarvoice')->getProductId($productDefault);

            $ioObject->streamWrite("<Product>\n".
                '    <ExternalId>'.$productExternalId."</ExternalId>\n".
                '    <Name>'.htmlspecialchars($productDefault->getName(), ENT_QUOTES, 'UTF-8')."</Name>\n".
                '    <Description>'.htmlspecialchars($productDefault->getShortDescription(), ENT_QUOTES, 'UTF-8')."</Description>\n");

            $brand = $productDefault->getBrand();
            if (!is_null($brand) && !empty($brand)) {
                $ioObject->streamWrite('    <Brand><ExternalId>' . $brand . "</ExternalId></Brand>\n");
            }
                
            /* Make sure that CategoryExternalId is one written to Category section */
            $parentCategories = $productDefault->getCategoryIds();
            if (!is_null($parentCategories) && count($parentCategories) > 0) {
                foreach ($parentCategories as $parentCategoryId) {
                    $parentCategory = Mage::getModel('catalog/category')->load($parentCategoryId);
                    if ($parentCategory != null) {
                        $categoryExternalId = Mage::helper('bazaarvoice')->getCategoryId($parentCategory);
                        if (in_array($categoryExternalId, $this->_categoryIdList)) {
                            $ioObject->streamWrite('    <CategoryExternalId>' . $categoryExternalId . "</CategoryExternalId>\n");
                            break;
                        }
                    }
                }                
            }
            
            $ioObject->streamWrite('    <ProductPageUrl>'.$productDefault->getProductUrl()."</ProductPageUrl>\n".
                '    <ImageUrl>'.$productDefault->getImageUrl()."</ImageUrl>\n");

            // Write out localized <Names>
            $ioObject->streamWrite("    <Names>\n");
            foreach($productViews as $curLocale => $curProduct) {
                $ioObject->streamWrite('        <Name locale="' . $curLocale . '">' . htmlspecialchars($curProduct->getName(), ENT_QUOTES, 'UTF-8')."</Name>\n");
            }
            $ioObject->streamWrite("    </Names>\n");
            // Write out localized <Descriptions>
            $ioObject->streamWrite("    <Descriptions>\n");
            foreach($productViews as $curLocale => $curProduct) {
                $ioObject->streamWrite('         <Description locale="' . $curLocale . '">' . htmlspecialchars($curProduct->getShortDescription(), ENT_QUOTES, 'UTF-8')."</Description>\n");
            }
            $ioObject->streamWrite("    </Descriptions>\n");
            // Write out localized <ProductPageUrls>
            $ioObject->streamWrite("    <ProductPageUrls>\n");
            foreach($productViews as $curLocale => $curProduct) {
                $ioObject->streamWrite('        <ProductPageUrl locale="' . $curLocale . '">' . $curProduct->getProductUrl() . "</ProductPageUrl>\n");
            }
            $ioObject->streamWrite("    </ProductPageUrls>\n");
            // Write out localized <ImageUrls>
            $ioObject->streamWrite("    <ImageUrls>\n");
            foreach($productViews as $curLocale => $curProduct) {
                $ioObject->streamWrite('        <ImageUrl locale="' . $curLocale . '">' . $curProduct->getImageUrl() . "</ImageUrl>\n");
            }
            $ioObject->streamWrite("    </ImageUrls>\n");            

            // Close this product
            $ioObject->streamWrite("</Product>\n");
        }
        if (count($productIds) > 0) {
            $ioObject->streamWrite("</Products>\n");
        }
    }

}
