<?php
/**
 * @author Bazaarvoice, Inc.
 */
class Bazaarvoice_Connector_Model_ProductFeed_Brand extends Mage_Core_Model_Abstract
{

    /**
     * @param Varien_Io_File $ioObject File object for feed file
     * @param Mage_Core_Model_Website $website
     */
    public function processBrandsForWebsite(Varien_Io_File $ioObject, Mage_Core_Model_Website $website)
    {
        // Get default store for website
        $defaultStore = $website->getDefaultGroup()->getDefaultStore();
        // Lookup default locale code
        $defaultLocaleCode = Mage::getStoreConfig('bazaarvoice/general/locale', $defaultStore->getId());
        // Check localeCode
        if (!strlen($defaultLocaleCode)) {
            Mage::throwException('Invalid locale code configured for store: ' . $defaultStore->getCode());
        }

        // Lookup the configured attribute code for "Brand"
        $attributeCode = Mage::getStoreConfig('bazaarvoice/bv_config/product_feed_brand_attribute_code', $defaultStore->getId());
        // If there is no attribute code for default store, then bail
        if(!strlen(trim($attributeCode))) {
            return;
        }

        // Look up options for each store
        $optionsByLocale = array();
        /** @var Mage_Core_Model_Store $store */
        foreach ($website->getStores() as $store) {
            // Get store locale
            $localeCode = Mage::getStoreConfig('bazaarvoice/general/locale', $store->getId());
            // Check localeCode
            if (!strlen($localeCode)) {
                Mage::throwException('Invalid locale code configured for store: ' . $store->getCode());
            }
            // Save options mapped to localeCode
            $optionsByLocale[$localeCode] = $this->getOptionsForStore($store);
        }

        // Now process brands in multi-store fashion
        $this->processBrandsMultiStore($ioObject, $defaultLocaleCode, $optionsByLocale);
    }

    /**
     * @param Varien_Io_File $ioObject File object for feed file
     * @param Mage_Core_Model_Store_Group $group
     */
    public function processBrandsForGroup(Varien_Io_File $ioObject, Mage_Core_Model_Store_Group $group)
    {
        // Get default store for group
        $defaultStore = $group->getDefaultStore();
        // Lookup default locale code
        $defaultLocaleCode = Mage::getStoreConfig('bazaarvoice/general/locale', $defaultStore->getId());
        // Check localeCode
        if (!strlen($defaultLocaleCode)) {
            Mage::throwException('Invalid locale code configured for store: ' . $defaultStore->getCode());
        }

        // Lookup the configured attribute code for "Brand"
        $attributeCode = Mage::getStoreConfig('bazaarvoice/bv_config/product_feed_brand_attribute_code', $defaultStore->getId());
        // If there is no attribute code for default store, then bail
        if(!strlen(trim($attributeCode))) {
            return;
        }

        // Look up options for each store
        $optionsByLocale = array();
        /** @var Mage_Core_Model_Store $store */
        foreach ($group->getStores() as $store) {
            // Get store locale
            $localeCode = Mage::getStoreConfig('bazaarvoice/general/locale', $store->getId());
            // Check localeCode
            if (!strlen($localeCode)) {
                Mage::throwException('Invalid locale code configured for store: ' . $store->getCode());
            }
            // Save options mapped to localeCode
            $optionsByLocale[$localeCode] = $this->getOptionsForStore($store);
        }

        // Now process brands in multi-store fashion
        $this->processBrandsMultiStore($ioObject, $defaultLocaleCode, $optionsByLocale);
    }

    /**
     * @param Varien_Io_File $ioObject File object for feed file
     * @param Mage_Core_Model_Store $store
     */
    public function processBrandsForStore(Varien_Io_File $ioObject, Mage_Core_Model_Store $store)
    {
        // Lookup the configured attribute code for "Brand"
        $attributeCode = Mage::getStoreConfig('bazaarvoice/bv_config/product_feed_brand_attribute_code', $store->getId());
        // If there is no attribute code for store, then bail
        if(!strlen(trim($attributeCode))) {
            return;
        }

        // Lookup up brand names
        $attributeOptions = $this->getOptionsForStore($store);
        // Output tag only if more than 1 brand
        if (count($attributeOptions) > 0) {
            $ioObject->streamWrite("<Brands>\n");
        }
        // Iterate brands and write each to file
        foreach($attributeOptions as $optionId => $optionValue) {
            $this->writeBrand($ioObject, $optionId, $optionValue);
        }
        if (count($attributeOptions) > 0) {
            $ioObject->streamWrite("</Brands>\n");
        }
    }

    protected function processBrandsMultiStore($ioObject, $defaultLocaleCode, $optionsByLocale)
    {
        // Recombine multi-dimensional array so its grouped by option id
        $allOptions = array();
        foreach($optionsByLocale as $localeCode => $options) {
            // Check if this store is default
            if($localeCode == $defaultLocaleCode) {
                $defaultOptions = $options;
            }
            // Add this store's options to main array
            foreach($options as $optionId => $optionValue) {
                if(!isset($allOptions[$optionId])) {
                    $allOptions[$optionId] = array();
                }
                $allOptions[$optionId][$localeCode] = $optionValue;
            }
        }
        // Output tag only if more than 1 brand
        if (count($allOptions) > 0) {
            $ioObject->streamWrite("<Brands>\n");
        }
        // Now iterate through all brands and write out
        foreach($allOptions as $optionId => $localeValues) {
            $this->writeBrandMultiLocale($ioObject, $optionId, $defaultOptions[$optionId], $localeValues);
        }
        if (count($allOptions) > 0) {
            $ioObject->streamWrite("</Brands>\n");
        }
    }

    protected function getOptionsForStore($store)
    {
        // Lookup the configured attribute code for "Brand"
        $attributeCode = Mage::getStoreConfig('bazaarvoice/bv_config/product_feed_brand_attribute_code', $store->getId());
        // Lookup the attribute options for this store
        /** @var Mage_Catalog_Model_Resource_Eav_Attribute $attribute */
        $attribute = Mage::getModel('catalog/resource_eav_attribute')->load($attributeCode, 'attribute_code');
        $attribute->setStoreId($store->getId());
        $attributeOptions = $attribute->getSource()->getAllOptions(false);
        // Reformat array
        $processedOptions = array();
        foreach ($attributeOptions as $attributeOption) {
            $processedOptions[$attributeOption['value']] = $attributeOption['label'];
        }

        return $processedOptions;
    }

    protected function writeBrand(Varien_Io_File $ioObject, $brandExternalId, $brandName)
    {
        $ioObject->streamWrite(
            "<Brand>\n" .
            "    <ExternalId>" . $brandExternalId . "</ExternalId>\n" .
            "    <Name><![CDATA[" . $brandName . "]]></Name>\n" .
            "</Brand>\n"
        );
    }

    protected function writeBrandMultiLocale(Varien_Io_File $ioObject, $brandExternalId, $defaultBrandName, $brandNames)
    {
        $ioObject->streamWrite(
            "<Brand>\n" .
            "    <ExternalId>" . $brandExternalId . "</ExternalId>\n" .
            "    <Name><![CDATA[" . $defaultBrandName . "]]></Name>\n" );
            // Write out localized <Names>
            $ioObject->streamWrite("    <Names>\n");
        foreach ($brandNames as $curLocale => $curBrandName) {
            $ioObject->streamWrite('        <Name locale="' . $curLocale . '"><![CDATA[' . $curBrandName . "]]></Name>\n");
        }
        $ioObject->streamWrite(
            "    </Names>\n" .
            "</Brand>\n"
        );
    }

}
