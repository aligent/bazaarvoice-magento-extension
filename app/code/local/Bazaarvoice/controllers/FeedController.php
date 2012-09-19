<?php
class Bazaarvoice_FeedController extends Mage_Core_Controller_Front_Action {
    public function preDispatch() {
        parent::preDispatch();
        if($this->getRequest()->getParam('bvauthenticateuser') == "true") {
            if (!Mage::getSingleton('customer/session')->authenticate($this)) {
                $this->setFlag('', self::FLAG_NO_DISPATCH, true);
            }
        }
    }

    public function inlineratingsAction() {
    	$rerf = new Bazaarvoice_Model_RetrieveInlineRatingsFeed();
        $rerf->retrieveInlineRatingsFeed();

        $this->loadLayout();
	$this->renderLayout();
    }
    public function productAction() {
    	$epf = new Bazaarvoice_Model_ExportProductFeed();
        $epf->exportDailyProductFeed();

        $this->loadLayout();
	$this->renderLayout();
    }
    public function smartseoAction() {
    	$seo = new Bazaarvoice_Model_RetrieveSmartSEOPackage();
        $seo->retrieveSmartSEOPackage();

        $this->loadLayout();
	$this->renderLayout();
    }
    public function ppeAction() {
    	$ppe = new Bazaarvoice_Model_ExportPurchaseFeed();
        $ppe->exportPurchaseFeed();

        $this->loadLayout();
	$this->renderLayout();
    }

}
?>