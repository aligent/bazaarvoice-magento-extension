<?php
class Bazaarvoice_Connector_FeedController extends Mage_Core_Controller_Front_Action
{
    public function preDispatch()
    {
        parent::preDispatch();
        if ($this->getRequest()->getParam('bvauthenticateuser') == 'true') {
            if (!Mage::getSingleton('customer/session')->authenticate($this)) {
                $this->setFlag('', self::FLAG_NO_DISPATCH, true);
            }
        }
    }

    public function inlineratingsAction()
    {
        $rerf = Mage::getModel('bazaarvoice/retrieveInlineRatingsFeed');
        $rerf->retrieveInlineRatingsFeed();

        $this->loadLayout();
        $this->renderLayout();
    }
    
    public function productAction()
    {
        $epf = Mage::getModel('bazaarvoice/exportProductFeed');
        $epf->exportDailyProductFeed();

        $this->loadLayout();
        $this->renderLayout();
    }
    
    public function smartseoAction()
    {
        $seo = Mage::getModel('bazaarvoice/retrieveSmartSEOPackage');
        $seo->retrieveSmartSEOPackage();

        $this->loadLayout();
        $this->renderLayout();
    }
    
    public function ppeAction()
    {
        $ppe = Mage::getModel('bazaarvoice/exportPurchaseFeed');
        $ppe->exportPurchaseFeed();

        $this->loadLayout();
        $this->renderLayout();
    }

}
