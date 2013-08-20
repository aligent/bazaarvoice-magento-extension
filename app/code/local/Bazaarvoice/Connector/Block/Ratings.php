<?php
class Bazaarvoice_Connector_Block_Ratings extends Mage_Core_Block_Template
{

    private $_isEnabled;

    public function _construct()
    {
        // enabled/disabled in admin
        $this->_isEnabled = Mage::getStoreConfig('bazaarvoice/rr/enable_inline_ratings') === '1'
                                && Mage::getStoreConfig('bazaarvoice/rr/enable_rr') === '1' 
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
    
    public function getLoadedProductCollection()
    {
        // Get reference to parent block - product list
        $productListBlock = $this->getParentBlock();
        // Verify the parent is really a product list
        if(!($productListBlock instanceof Mage_Catalog_Block_Product_List)) {
            // Return empty array to keep template code happy
            return array();
        }
        // Get and return ref to loaded prod collection
        return $productListBlock->getLoadedProductCollection();
    }
    
}
