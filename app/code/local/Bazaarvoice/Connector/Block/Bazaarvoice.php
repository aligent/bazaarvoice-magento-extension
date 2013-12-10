<?php
class Bazaarvoice_Connector_Block_Bazaarvoice extends Mage_Core_Block_Template
{

    /**
     *  NOTE:  This class isn't used by the current Bazaarvoice integration, but is left
     *         here as a placeholder for future integration.
     */


    public function _prepareLayout()
    {
        return parent::_prepareLayout();
    }
    
    public function getRatingsandreviews()
    {
        if (!$this->hasData('ratingsandreviews')) {
            $this->setData('ratingsandreviews', Mage::registry('ratingsandreviews'));
        }
        return $this->getData('ratingsandreviews');
    }

}