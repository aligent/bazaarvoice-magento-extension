<?php
class Bazaarvoice_Connector_Block_Submissioncontainer extends Mage_Core_Block_Template
{

    public function _prepareLayout()
    {
        parent::_prepareLayout();

        if ($headBlock = $this->getLayout()->getBlock('head')) {
            $headBlock->addLinkRel('canonical', $this->getUrl('bazaarvoice'));
        }

        return $this;
    }
    


}