<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to commercial source code license 
 * of StoreFront Consulting, Inc.
 *
 * @copyright	(C)Copyright 2016 StoreFront Consulting, Inc (http://www.StoreFrontConsulting.com/)
 * @package		Bazaarvoice_Connector
 * @author		Dennis Rogers <dennis@storefrontconsulting.com>
 */

class Bazaarvoice_Connector_Model_Source_Lookback extends Mage_Core_Model_Config_Data
{
    protected function _beforeSave()
    {
        $lookback = $this->getValue();
        $lookback = preg_replace('#[^0-9]#','', $lookback);

        if($lookback < 30) {
            Mage::throwException(Mage::helper('adminhtml')->__('Minimum lookback is 30 days.'));
        }
        
        $this->setValue($lookback);
        
    }
}
