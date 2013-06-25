<?php

class Bazaarvoice_Connector_Model_Source_Environment
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => 'staging',
                'label' => Mage::helper('bazaarvoice')->__('Staging')
            ),
            array(
                'value' => 'production',
                'label' => Mage::helper('bazaarvoice')->__('Production')
            ),
        );
    }
}
