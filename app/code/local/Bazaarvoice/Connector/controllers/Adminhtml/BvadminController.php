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

class Bazaarvoice_Connector_Adminhtml_BvadminController extends Mage_Adminhtml_Controller_Action
{
    public function purchaseAction()
    {
    	// Create model
    	$exportModel = Mage::getModel('bazaarvoice/exportPurchaseFeed');
    
    	// Call export
    	$exportModel->exportPurchaseFeed();
    	
    	echo "Feed complete.";
    }
}