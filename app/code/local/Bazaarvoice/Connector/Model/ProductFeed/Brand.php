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
        $store = $website->getDefaultGroup()->getDefaultStore();
        // Call for default store
        $this->processBrandsForStore($ioObject, $store);
    }

    /**
     * @param Varien_Io_File $ioObject File object for feed file
     * @param Mage_Core_Model_Store_Group $group
     */
    public function processBrandsForGroup(Varien_Io_File $ioObject, Mage_Core_Model_Store_Group $group)
    {
        // Get default store for group
        $store = $group->getDefaultStore();
        // Call for default store
        $this->processBrandsForStore($ioObject, $store);
    }

    /**
     * @param Varien_Io_File $ioObject File object for feed file
     * @param Mage_Core_Model_Store $store
     */
    public function processBrandsForStore(Varien_Io_File $ioObject, Mage_Core_Model_Store $store)
    {
        // Lookup the configured attribute code for "Brand"
        $attributeCode = Mage::getStoreConfig('bazaarvoice/bv_config/product_feed_brand_attribute_code', $store->getId());
        // Lookup the attribute options for this store
        /** @var Mage_Catalog_Model_Resource_Eav_Attribute $attribute */
        $attribute = Mage::getModel('catalog/resource_eav_attribute')->load($attributeCode, 'attribute_code');
        $attributeOptions = $attribute->getSource()->getAllOptions(false);

        // Iterate brands and write each to file
        foreach($attributeOptions as $optionId => $optionValue) {
            $this->writeBrand($ioObject, $optionId, $optionValue);
        }
    }

    /**
     * @param Varien_Io_File $ioObject File object for feed file
     */
    protected function writeBrand(Varien_Io_File $ioObject, $brandId, $brandName)
    {
        // Get ref to BV helper
        /* @var $bvHelper Bazaarvoice_Connector_Helper_Data */
        $bvHelper = Mage::helper('bazaarvoice');

        // Get external id
        $brandExternalId = $brandId;

        $ioObject->streamWrite(
            "<Brand>\n" .
            "    <ExternalId>" . $brandExternalId . "</ExternalId>\n" .
            "    <Name><![CDATA[" . $brandName . "]]></Name>\n" .
            "</Brand>\n"
        );
    }

}
