<?php
/**
 *
 */

require 'app/Mage.php';

if (!Mage::isInstalled()) {
    echo "Application is not installed yet, please complete install wizard first.";
    exit;
}

// Only for urls
// Don't remove this
$_SERVER['SCRIPT_NAME'] = str_replace(basename(__FILE__), 'index.php', $_SERVER['SCRIPT_NAME']);
$_SERVER['SCRIPT_FILENAME'] = str_replace(basename(__FILE__), 'index.php', $_SERVER['SCRIPT_FILENAME']);

Mage::app('admin')->setUseSessionInUrl(false);

umask(0);

try {
	// Log mem usage
	echo "\n" . 'Memory usage: ' . memory_get_usage() . "\n";

	// Create model
	$exportModel = Mage::getModel('bazaarvoice/exportProductFeed');

	// Call export
	$exportModel->exportDailyProductFeed();
	
	// Log mem usage
	echo 'Memory usage: ' . memory_get_usage() . "\n";

} catch (Exception $e) {
	Mage::printException($e);
}

