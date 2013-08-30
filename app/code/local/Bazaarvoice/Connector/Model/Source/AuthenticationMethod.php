<?php
 /**
  * Source model for authentication method
  */
class Bazaarvoice_Connector_Model_Source_AuthenticationMethod
{

    const BV_HOSTED_AUTH = 'bv';
    const MAGENTO_SITE_AUTH = 'magento';

    public function toOptionArray()
    {
        return array(
            array(
                'value' => self::BV_HOSTED_AUTH,
                'label' => Mage::helper('bazaarvoice')->__('BV Hosted Authentication')
            ),
            array(
                'value' => self::MAGENTO_SITE_AUTH,
                'label' => Mage::helper('bazaarvoice')->__('Magento Site Authentication')
            )
        );
    }
    
}
