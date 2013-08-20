<?php

include 'Bazaarvoice/bvseosdk.php';

class Bazaarvoice_Connector_Block_Reviews extends Mage_Core_Block_Template
{

    private $_isEnabled;

    public function _construct()
    {
        // enabled/disabled in admin
        $this->_isEnabled = Mage::getStoreConfig('bazaarvoice/rr/enable_rr') === '1' 
                                && Mage::getStoreConfig('bazaarvoice/General/enable_bv') === '1';
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
        if(Mage::getStoreConfig('bazaarvoice/General/enable_cloud_seo') === '1' && $this->getIsEnabled()) {
            $bv = new BV(array(
                'deployment_zone_id' => Mage::getStoreConfig('bazaarvoice/bv_config/display_code'), // replace with your display code (BV provided)
                'product_id' => Mage::helper('bazaarvoice')->getProductId(Mage::registry('current_product')), // replace with product id 
                'cloud_key' => Mage::getStoreConfig('bazaarvoice/General/cloud_seo_key'), // BV provided value
                'staging' => TRUE
            ));    
            $seoContent = $bv->reviews->renderSeo();
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
        return array_merge(parent::getCacheTags(), Mage::registry('current_product')->getSku());
    }
    
}
