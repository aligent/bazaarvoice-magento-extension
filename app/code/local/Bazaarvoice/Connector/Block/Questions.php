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
            $product = Mage::registry('current_product');  
            $productUrl = Mage::helper('core/url')->getCurrentUrl();
            $parts = parse_url($productUrl);
            if(isset($parts['query'])) {
                parse_str($parts['query'], $query);
                unset($query['bvrrp']);
                $baseUrl = $parts['scheme'] . '://' . $parts['host'] . $parts['path'] . '?' . http_build_query($query);
            } else {
                $baseUrl = $productUrl;
            }              
            $params = array(
                'seo_sdk_enabled' => TRUE,
                'bv_root_folder' => $deploymentZoneId, // replace with your display code (BV provided)
                'subject_id' => Mage::helper('bazaarvoice')->getProductId($product), // replace with product id 
                'cloud_key' => Mage::getStoreConfig('bazaarvoice/general/cloud_seo_key'), // BV provided value
                'base_url' => $baseUrl,
                'page_url' => $productUrl,
                'staging' => (Mage::getStoreConfig('bazaarvoice/general/environment') == "staging" ? TRUE : FALSE)
            );
            if($this->getRequest()->getParam('bvreveal') == 'debug')
                $params['bvreveal'] = 'debug';
            
            try{
                $bv = new BV($params);
                $seoContent = $bv->questions->getContent();
                $seoContent .= '<!-- BV Questions Parameters: ' . print_r($params, 1) . '-->';
            } Catch (Exception $e) {
                Mage::logException($e);
                return;
            }
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
