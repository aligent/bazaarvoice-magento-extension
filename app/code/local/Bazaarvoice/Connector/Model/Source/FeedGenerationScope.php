<?php
 /**
  * Source model for feed gen scope
  */
class Bazaarvoice_Connector_Model_Source_FeedGenerationScope
{

    const SCOPE_WEBSITE = 'website';
    const SCOPE_STORE_GROUP = 'group';
    const SCOPE_STORE_VIEW = 'view';

    public function toOptionArray()
    {
        return array(
            array(
                'value' => self::SCOPE_WEBSITE,
                'label' => Mage::helper('bazaarvoice')->__('Magento Website')
            ),
            array(
                'value' => self::SCOPE_STORE_GROUP,
                'label' => Mage::helper('bazaarvoice')->__('Magento Store / Store Group')
            ),
            array(
                'value' => self::SCOPE_STORE_VIEW,
                'label' => Mage::helper('bazaarvoice')->__('Magento Store View')
            ),
        );
    }
    
}
