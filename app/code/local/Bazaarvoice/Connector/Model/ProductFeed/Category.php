<?php
/**
 * @author Bazaarvoice, Inc.
 */
class Bazaarvoice_Connector_Model_ProductFeed_Category extends Mage_Core_Model_Abstract
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
     * @param Varien_Io_File $ioObject File object for feed file
     * @param Mage_Core_Model_Website $website
     */
    public function processCategoriesForWebsite(Varien_Io_File $ioObject, Mage_Core_Model_Website $website)
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
            ->addAttributeToFilter('path', array('like' => $rootCategoryPath . '/%'));
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
                // Capture localized URL in extra var
                $category->setData('localized_url', $this->getCategoryUrl($category));
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
    public function processCategoriesForGroup(Varien_Io_File $ioObject, Mage_Core_Model_Store_Group $group)
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
                // Capture localized URL in extra var
                $category->setData('localized_url', $this->getCategoryUrl($category));
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
    public function processCategoriesForStore(Varien_Io_File $ioObject, Mage_Core_Model_Store $store)
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
            // Capture localized URL in extra var
            $categoryDefault->setData('localized_url', $this->getCategoryUrl($categoryDefault));
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
    protected function writeCategory(Varien_Io_File $ioObject, Mage_Catalog_Model_Category $categoryDefault, array $categoriesByLocale)
    {
        // Get ref to BV helper
        /* @var $bvHelper Bazaarvoice_Connector_Helper_Data */
        $bvHelper = Mage::helper('bazaarvoice');

        // Get external id
        $categoryExternalId = $bvHelper->getCategoryId($categoryDefault, $categoryDefault->getStoreId());

        $categoryName = htmlspecialchars($categoryDefault->getName(), ENT_QUOTES, 'UTF-8', false);
        $categoryPageUrl = htmlspecialchars($categoryDefault->getData('localized_url'), ENT_QUOTES, 'UTF-8', false);

        $parentExtId = '';
        /* @var $parentCategory Mage_Catalog_Model_Category */
        $parentCategory = Mage::getModel('catalog/category')->setStoreId($categoryDefault->getStoreId())->load($categoryDefault->getParentId());
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
                htmlspecialchars($curCategory->getName(), ENT_QUOTES, 'UTF-8') . "]]></Name>\n", false);
        }
        $ioObject->streamWrite("    </Names>\n");
        // Write out localized <CategoryPageUrls>
        $ioObject->streamWrite("    <CategoryPageUrls>\n");
        /* @var $curCategory Mage_Catalog_Model_Category */
        foreach ($categoriesByLocale as $curLocale => $curCategory) {
            $ioObject->streamWrite('        <CategoryPageUrl locale="' . $curLocale . '">' . "<![CDATA[" .
                htmlspecialchars($curCategory->getData('localized_url'), ENT_QUOTES, 'UTF-8', false) . "]]>" . "</CategoryPageUrl>\n");
        }
        $ioObject->streamWrite("    </CategoryPageUrls>\n");

        $ioObject->streamWrite("</Category>\n");

    }

    /**
     * Method to retrieve Magento category URL, modeled after Mage_Catalog_Model_Category::getUrl in EE 1.12
     *
     * @param Mage_Catalog_Model_Category $category
     * @return string
     */
    protected function getCategoryUrl(Mage_Catalog_Model_Category $category)
    {
        /** @var Mage_Core_Model_Url $urlInstance */
        $urlInstance = Mage::getModel('core/url');
        $urlInstance->setStore($category->getStoreId());
        $url = $category->_getData('url');
        if (is_null($url)) {
            Varien_Profiler::start('REWRITE: '.__METHOD__);

            if ($category->hasData('request_path') && $category->getRequestPath() != '') {
                $category->setData('url', $urlInstance->getDirectUrl($category->getRequestPath()));
                Varien_Profiler::stop('REWRITE: '.__METHOD__);
                return $category->getData('url');
            }

            Varien_Profiler::stop('REWRITE: '.__METHOD__);

            $rewrite = $category->getUrlRewrite();
            if ($category->getStoreId()) {
                $rewrite->setStoreId($category->getStoreId());
            }
            $idPath = 'category/' . $category->getId();
            $rewrite->loadByIdPath($idPath);

            if ($rewrite->getId()) {
                Mage::log('request path: ' . $rewrite->getRequestPath(), Zend_Log::DEBUG, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
                $category->setData('url', $urlInstance->getDirectUrl($rewrite->getRequestPath()));
                Varien_Profiler::stop('REWRITE: '.__METHOD__);
                return $category->getData('url');
            }

            Varien_Profiler::stop('REWRITE: '.__METHOD__);

            $category->setData('url', $category->getCategoryIdUrl());
            return $category->getData('url');
        }
        return $url;
    }

}
