<?php

require_once('Bazaarvoice/bvseosdk.php');

class Bazaarvoice_Connector_Block_Questions extends Mage_Core_Block_Template
{

    private $_isEnabled;

    public function _construct()
    {
        // enabled/disabled in admin
        $this->_isEnabled = Mage::getStoreConfig('bazaarvoice/qa/enable_qa') === '1' 
                                && Mage::getStoreConfig('bazaarvoice/general/enable_bv') === '1';
    }

    /**
     * returns true if feature is enabled in admin, otherwise returns false
     * @return bool
     */
    public function getIsEnabled()
    {
        return $this->_isEnabled;
    }
    
    public function getSEOContent()
    {
        $seoContent = '';
        if(Mage::getStoreConfig('bazaarvoice/general/enable_cloud_seo') === '1' && $this->getIsEnabled()) {
            // Check if admin has configured a legacy display code
            if(strlen(Mage::getStoreConfig('bazaarvoice/bv_config/display_code'))) {
                $deploymentZoneId =
                    Mage::getStoreConfig('bazaarvoice/bv_config/display_code') .
                    '-' . Mage::getStoreConfig('bazaarvoice/general/locale');
            }
            else {
                $deploymentZoneId =
                    str_replace(' ', '_', Mage::getStoreConfig('bazaarvoice/general/deployment_zone')) .
                    '-' . Mage::getStoreConfig('bazaarvoice/general/locale');
            }
            $bv = new BV(array(
                'deployment_zone_id' => $deploymentZoneId, // replace with your display code (BV provided)
                'product_id' => Mage::helper('bazaarvoice')->getProductId(Mage::registry('current_product')), // replace with product id 
                'cloud_key' => Mage::getStoreConfig('bazaarvoice/general/cloud_seo_key'), // BV provided value
                'staging' => (Mage::getStoreConfig('bazaarvoice/general/environment') == "staging" ? TRUE : FALSE)
            ));    
            $seoContent = $bv->questions->renderSeo();
        }
        
        return $seoContent;
    }

    /**
     * Retrieve block cache tags based on category
     *
     * @return array
     */
    public function getCacheTags()
    {
        return array_merge(parent::getCacheTags(), Mage::registry('current_product')->getCacheIdTags());
    }
    
}
