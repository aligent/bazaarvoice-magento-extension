<?php
/**
 * @author Bazaarvoice, Inc.
 */
class Bazaarvoice_Connector_Model_ProductFeed_Product extends Mage_Core_Model_Abstract
{
    private $_categoryIdList = array();

    public function setCategoryIdList(array $list)
    {
        $this->_categoryIdList = $list;
    }

    public function getCategoryIdList()
    {
        return $this->_categoryIdList;
    }

    /**
     *
     * @param Varien_Io_File $ioObject File object for feed file
     * @param Mage_Core_Model_Website $website
     */
    public function processProductsForWebsite(Varien_Io_File $ioObject, Mage_Core_Model_Website $website)
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
                // Set localized product and image url
                $product->setData('localized_image_url', $this->getProductImageUrl($product));
                // Set bazaarvoice specific attributes
                // Brand
                $brandAttributeCode = Mage::getStoreConfig('bazaarvoice/bv_config/product_feed_brand_attribute_code', $store->getId());
                if (strlen(trim($brandAttributeCode))) {
                    $brand = $product->getData($brandAttributeCode);
                    $product->setData('brand', $brand);
                }
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
    public function processProductsForGroup(Varien_Io_File $ioObject, Mage_Core_Model_Store_Group $group)
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
                // Set localized product and image url
                $product->setData('localized_image_url', $this->getProductImageUrl($product));
                // Set bazaarvoice specific attributes
                $brandAttributeCode = Mage::getStoreConfig('bazaarvoice/bv_config/product_feed_brand_attribute_code', $store->getId());
                if (strlen(trim($brandAttributeCode))) {
                    $brand = $product->getData($brandAttributeCode);
                    $product->setData('brand', $brand);
                }
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
    public function processProductsForStore(Varien_Io_File $ioObject, Mage_Core_Model_Store $store)
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
            // Set localized product and image url
            $productDefault->setData('localized_image_url', $this->getProductImageUrl($productDefault));
            // Set bazaarvoice specific attributes
            $brandAttributeCode = Mage::getStoreConfig('bazaarvoice/bv_config/product_feed_brand_attribute_code', $store->getId());
            if (strlen(trim($brandAttributeCode))) {
                $brand = $productDefault->getData($brandAttributeCode);
                $productDefault->setData('brand', $brand);
            }
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
    protected function writeProduct(Varien_Io_File $ioObject, Mage_Catalog_Model_Product $productDefault,
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

        $brandId = $productDefault->getData('brand');
        if ($productDefault->hasData('brand') && !is_null($brandId) && !empty($brandId)) {
            $ioObject->streamWrite('    <BrandExternalId>' . $brandId . "</BrandExternalId>\n");
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

        $ioObject->streamWrite('    <ProductPageUrl>' . "<![CDATA[" . $this->getProductUrl($productDefault) . "]]>" . "</ProductPageUrl>\n");
        $imageUrl = $productDefault->getData('localized_image_url');
        if (strlen($imageUrl)) {
            $ioObject->streamWrite('    <ImageUrl>' . "<![CDATA[" . $imageUrl . "]]>" . "</ImageUrl>\n");
        }

        // Write out localized <Names>
        $ioObject->streamWrite("    <Names>\n");
        foreach ($productsByLocale as $curLocale => $curProduct) {
            $ioObject->streamWrite('        <Name locale="' . $curLocale . '"><![CDATA[' .
                htmlspecialchars($curProduct->getData('name'), ENT_QUOTES, 'UTF-8') . "]]></Name>\n");
        }
        $ioObject->streamWrite("    </Names>\n");
        // Write out localized <Descriptions>
        $ioObject->streamWrite("    <Descriptions>\n");
        foreach ($productsByLocale as $curLocale => $curProduct) {
            $ioObject->streamWrite('         <Description locale="' . $curLocale . '"><![CDATA[' .
                htmlspecialchars($curProduct->getData('short_description'), ENT_QUOTES, 'UTF-8') . "]]></Description>\n");
        }
        $ioObject->streamWrite("    </Descriptions>\n");
        // Write out localized <ProductPageUrls>
        $ioObject->streamWrite("    <ProductPageUrls>\n");
        foreach ($productsByLocale as $curLocale => $curProduct) {
            $ioObject->streamWrite('        <ProductPageUrl locale="' . $curLocale . '">' . "<![CDATA[" .
                $this->getProductUrl($curProduct) . "]]>" . "</ProductPageUrl>\n");
        }
        $ioObject->streamWrite("    </ProductPageUrls>\n");
        // Write out localized <ImageUrls>
        $ioObject->streamWrite("    <ImageUrls>\n");
        foreach ($productsByLocale as $curLocale => $curProduct) {
            $imageUrl = $curProduct->getData('localized_image_url');
            if (strlen($imageUrl)) {
                $ioObject->streamWrite('        <ImageUrl locale="' . $curLocale . '">' . "<![CDATA[" . $imageUrl .
                    "]]>" . "</ImageUrl>\n");
            }
        }
        $ioObject->streamWrite("    </ImageUrls>\n");

        // Close this product
        $ioObject->streamWrite("</Product>\n");
    }

    protected function getProductImageUrl(Mage_Catalog_Model_Product $product)
    {
        try {
            // Init return var
            $imageUrl = null;
            // Get store id from product
            $storeId = $product->getStoreId();
            // Get image url from helper (this is for the default store
            $defaultStoreImageUrl = Mage::helper('catalog/image')->init($product, 'image');
            // Get media base url for correct store
            $mediaBaseUrl = Mage::app()->getStore($storeId)->getBaseUrl('media');
            // Get default media base url
            $defaultMediaBaseUrl = Mage::getBaseUrl('media');
            // Replace media base url component
            $imageUrl = str_replace($defaultMediaBaseUrl, $mediaBaseUrl, $defaultStoreImageUrl);

            // Return resulting url
            return $imageUrl;
        }
        catch (Exception $e) {
            Mage::log('Failed to get image URL for product sku: ' . $product->getSku());
            Mage::log('Continuing generating feed.');

            return '';
        }
    }

    protected function getProductUrl(Mage_Catalog_Model_Product $product)
    {
        $productUrl = $product->getProductUrl(false);
        // Trim any url params
        $questionMarkPos = strpos($productUrl, '?');
        if($questionMarkPos !== FALSE) {
            $productUrl = substr($productUrl, 0, $questionMarkPos);
        }

        return $productUrl;
    }

}
