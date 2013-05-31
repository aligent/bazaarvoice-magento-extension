<?php
class Bazaarvoice_Connector_ProfileController extends Mage_Core_Controller_Front_Action {
    public function preDispatch() {
        parent::preDispatch();
        if($this->getRequest()->getParam('bvauthenticateuser') == "true") {
            if (!Mage::getSingleton('customer/session')->authenticate($this)) {
                $this->setFlag('', self::FLAG_NO_DISPATCH, true);
            }
        }
    }

    public function editAction() {
        $this->loadLayout();
        $this->renderLayout();
    }

    public function displayAction() {
        $this->loadLayout();
        $this->renderLayout();
    }
}
