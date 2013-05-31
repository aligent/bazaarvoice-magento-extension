<?php
/**
 * @author Bazaarvoice, Inc.
 */
class Bazaarvoice_Connector_Model_RetrieveSmartSEOPackage extends Mage_Core_Model_Abstract {

    protected function _construct() {}

    public function retrieveSmartSEOPackage() {
        Mage::log("Start Bazaarvoice Smart SEO feed import");
        if(Mage::getStoreConfig("bazaarvoice/SmartSEOFeed/EnableSmartSEO") === "1") {

            $localFilePath = Mage::getBaseDir("var") . DS . "import" . DS . "bvfeeds";
            $localExtractsPath = $localFilePath . DS . "bvsmartseo";

            $gzLocalFilename = Mage::getStoreConfig("bazaarvoice/SmartSEOFeed/FeedFileName");
            $remoteFile = "/" . Mage::getStoreConfig("bazaarvoice/SmartSEOFeed/FeedPath") . "/" . Mage::getStoreConfig("bazaarvoice/SmartSEOFeed/FeedFileName");

            // Make sure the $remoteFile is tar.gz and not .zip (BV can create either - but Magento has no ability to deal with .zip)
            $desiredExt = ".tar.gz";
            if (substr_compare($remoteFile, $desiredExt, -strlen($desiredExt), strlen($desiredExt)) !== 0) {
                $msg = "BV - Unable to retrieve and process a .zip SmartSEO feed.  Only .tar.gz SmartSEO feeds can be processed by this extension";
                Mage::log($msg);
                die($msg);
            }

            $gzInterface = new Mage_Archive_Gz();
            $tarInterface = new Mage_Archive_Tar();

            // Clear away any previous download that may have existed.
            if (file_exists($localFilePath . DS . $gzLocalFilename)) {
                unlink($localFilePath . DS . $gzLocalFilename);
            }

            // Download the file
            if (!Mage::helper('bazaarvoice')->downloadFile($localFilePath, $gzLocalFilename, $remoteFile)) {
                // Unable to download the file.

                if (!file_exists($localExtractsPath)) {
                    // Couldn't download the file and no old SmartSEO files already exist on the filesystem
                    $subject = "Bazaarvoice SmartSEO Content Unavailable";
                    $msg = "The Bazaarvoice extension in your Magento store was unable to download new SmartSEO files from the Bazaarvoice server and there were no pre-existing SmartSEO files already in your Magento store.";
                    Mage::helper('bazaarvoice')->sendNotificationEmail($subject, $msg);
                    Mage::log($msg);
                    die($msg);
                }


                $lastModificationTime = filemtime($localExtractsPath);  // num seconds since EPOCH
                $maxStaleDays = Mage::getStoreConfig("bazaarvoice/SmartSEOFeed/MaxStaleDays");
                if (empty($maxStaleDays) || !is_numeric($maxStaleDays) || $maxStaleDays < 0) {
                    $maxStaleDays = 5;
                }
                if ((time() - $lastModificationTime) > ($maxStaleDays * 24 * 60 * 60)) {
                    // Couldn't download the file, and the old files that we DO have are too old.
                    $ioObject = new Varien_Io_File();
                    $ioObject->rmdir($localExtractsPath, true); // The "true" indicates recursive delete

                    $subject = "Bazaarvoice SmartSEO Content Unavailable";
                    $msg = "The Bazaarvoice extension in your Magento store was unable to download new SmartSEO files from the Bazaarvoice server and the existing SmartSEO files that are already in the Magento store have expired.";
                    Mage::helper('bazaarvoice')->sendNotificationEmail($subject, $msg);
                    Mage::log($msg);
                    die($msg);
                } else {
                    // Couldn't download the file, but the old files that we already have are still usable
                    $subject = "Bazaarvoice SmartSEO Content Couldn't be Updated";
                    $msg = "The Bazaarvoice extension in your Magento store was unable to download new SmartSEO files from the Bazaarvoice server.  The existing files will continue to be used.";
                    Mage::helper('bazaarvoice')->sendNotificationEmail($subject, $msg);
                    Mage::log($msg);
                    die($msg);
                }

            } else {
                // Successfully downloaded the file.

                // Clear out the existing extracts and recreate the dir.
                $ioObject = new Varien_Io_File();
                $ioObject->rmdir($localExtractsPath, true); // The "true" indicates recursive delete
                $ioObject->mkdir($localExtractsPath);

                // Move the archive into the extracts folder.  Use the native PHP function instead of Varien_Io_File
                // since Varien_Io_File throws unnecessary warnings attempting to change working directories
                rename($localFilePath . DS . $gzLocalFilename, $localExtractsPath . DS . $gzLocalFilename);

                // Decompress the file
                $tmpTarFilename = "smartseo-tmp.tar";
                $gzInterface->unpack($localExtractsPath . DS . $gzLocalFilename, $localExtractsPath . DS . $tmpTarFilename);
                unlink($localExtractsPath . DS . $gzLocalFilename);

                // Expand the .tar archive
                $tarInterface->unpack($localExtractsPath . DS . $tmpTarFilename, $localExtractsPath . DS);
                unlink($localExtractsPath . DS . $tmpTarFilename);
            }

            

        }
        Mage::log("End Bazaarvoice Smart SEO feed import");
    }
}
