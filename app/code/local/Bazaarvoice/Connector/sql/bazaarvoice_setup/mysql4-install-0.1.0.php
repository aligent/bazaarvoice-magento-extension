<?php
/* @var $installer Mage_Sales_Model_Mysql4_Setup */
$installer = $this;

$installer->startSetup();

/**
* Adds an attribute of the code sent_in_bv_postpurchase_feed to the Order object.
* As this is a flat table, it adds the column to the table (SALES_FLAT_ORDER).
**/

Mage::log("BV: Installing v0.1.0");
$installer->addAttribute('order', Bazaarvoice_Connector_Model_ExportPurchaseFeed::ALREADY_SENT_IN_FEED_FLAG, array('type'=>'int'));

$installer->endSetup();

