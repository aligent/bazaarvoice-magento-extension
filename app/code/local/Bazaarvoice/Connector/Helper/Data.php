<?php

class Bazaarvoice_Connector_Helper_Data extends Mage_Core_Helper_Abstract
{

    const BV_SUBJECT_TYPE = 'bvSubjectType';
    const BV_EXTERNAL_SUBJECT_NAME = 'bvExternalSubjectName';
    const BV_EXTERNAL_SUBJECT_ID = 'bvExternalSubjectID';
    
    const LOG_FILE = 'bazaarvoice.log';

    const CONST_SMARTSEO_BVRRP = 'bvrrp';
    const CONST_SMARTSEO_BVQAP = 'bvqap';
    const CONST_SMARTSEO_BVSYP = 'bvsyp';
    
    /**
     * Get the uniquely identifying product ID for a catalog product.
     *
     * This is the unique, product family-level id (duplicates are unacceptable).
     * If a product has its own page, this is its product ID. It is not necessarily
     * the SKU ID, as we do not collect separate Ratings & Reviews for different
     * styles of product - i.e. the 'Blue' vs. 'Red Widget'.
     *
     * @static
     * @param  $product a reference to a catalog product object
     * @return The unique product ID to be used with Bazaarvoice
     */
    public function getProductId($product)
    {
        $rawProductId = $product->getSku();

        // >> Customizations go here
        $rawProductId = preg_replace_callback('/\./s', create_function('$match','return "_bv".ord($match[0])."_";'), $rawProductId);        
        // << No further customizations after this
        
        return $this->replaceIllegalCharacters($rawProductId);

    }

    /**
     * Returns a product object that has the provided external ID.  This is a complementary
     * function to getProductId above.
     *
     * @static
     * @param  $productExternalId
     * @return product object for the provided external ID, or null if no match is found.
     */
    public function getProductFromProductExternalId($productExternalId)
    {
        $rawId = $this->reconstructRawId($productExternalId);

        $model = Mage::getModel('catalog/product');

        $productCollection = $model->getCollection()->addAttributeToSelect('*')
                                                    ->addAttributeToFilter('sku', $rawId)
                                                    ->load();


        foreach ($productCollection as $product) {
            // return the first one
            return $product;
        }

        return null;
    }

    /**
     * Get the uniquely identifying category ID for a catalog category.
     *
     * This is the unique, category or subcategory ID (duplicates are unacceptable).
     * This ID should be stable: it should not change for the same logical category even
     * if the category's name changes.
     *
     * @static
     * @param  $category a reference to a catalog category object
     * @return The unique category ID to be used with Bazaarvoice
     */
    public function getCategoryId($category, $storeId = null)
    {
        // Check config setting to see if we should use Magento category id
        $useUrlPath = Mage::getStoreConfig('bazaarvoice/bv_config/category_id_use_url_path', $storeId);
        $useUrlPath = (strtoupper($useUrlPath) == 'TRUE' || $useUrlPath == '1');
        if(!$useUrlPath) {
            return $category->getId();
        }
        else {
            // Generate a unique id based on category path
            // Start with url path
            $rawCategoryId = $category->getUrlPath();
            // Replace slashes with dashes in url path
            $rawCategoryId = str_replace('/', '-', $rawCategoryId);
            // Replace any illegal characters
            return $this->replaceIllegalCharacters($rawCategoryId);
        }
    }

    public function getBrandId($product)
    {
        // Get brand
        $brand = $product->getData('brand');
        // Replace any illegal characters
        $brandId = $this->replaceIllegalCharacters($brand);

        return $brandId;
    }

    /**
     * This unique ID can only contain alphanumeric characters (letters and numbers
     * only) and also the asterisk, hyphen, period, and underscore characters. If your
     * product IDs contain invalid characters, simply replace them with an alternate
     * character like an underscore. This will only be used in the feed and not for
     * any customer facing purpose.
     *
     * @static
     * @param  $rawId
     * @return mixed
     */
    public function replaceIllegalCharacters($rawId)
    {
        // We need to use a reversible replacement so that we can reconstruct the original ID later.
        // Example rawId = qwerty$%@#asdf
        // Example encoded = qwerty_bv36__bv37__bv64__bv35_asdf

        return preg_replace_callback('/[^\w\d\*-\._]/s', create_function('$match','return "_bv".ord($match[0])."_";'), $rawId);
    }

    public function reconstructRawId($externalId) {
        return preg_replace_callback('/_bv(\d*)_/s', create_function('$match','return chr($match[1]);'), $externalId);
    }
    
    /* general sftp connection call */
    public function connectSFTP($store = null) {
        // Get credentials for SFTP Connection
        $sftpHost = $this->getSFTPHost();
        $sftpUser = strtolower(Mage::getStoreConfig('bazaarvoice/general/client_name', $store));
        $sftpPw = Mage::getStoreConfig('bazaarvoice/general/ftp_password', $store);
        $sftp = Mage::helper('bazaarvoice/sftpConnection');

        if (!$sftp->connect($sftpHost, 22, $sftpUser, $sftpPw)) {
            Mage::throwException('    BV - SFTP connection attempt failed!', Zend_Log::ERR, self::LOG_FILE);
        }
        return $sftp;
    }

    /**
     * Connects to Bazaarvoice SFTP server and retrieves remote file to a local directory.
     * Local directory will be created if it doesn't exist.  Returns false if there
     * are any problems downloading the file.  Otherwise returns true.
     *
     * @static
     * @param  $localFilePath
     * @param  $localFileName
     * @param  $remoteFile
     * @return boolean
     */
    public function downloadFile($localFilePath, $localFileName, $remoteFile, $store = null)
    {
        Mage::log('    BV - starting download from Bazaarvoice server', Zend_Log::INFO, self::LOG_FILE);

        // Create the directory if it doesn't already exist.
        $ioObject = new Varien_Io_File();
        try {
            if (!$ioObject->fileExists($localFilePath, false)) {
                $ioObject->mkdir($localFilePath, 0777, true);
            }
        } catch (Exception $e) {
            // Most likely not enough permissions.
            Mage::log("    BV - failed attempting to create local directory '".$localFilePath."' to download feed.  Error trace follows: " . $e->getTraceAsString(), Zend_Log::ERR, self::LOG_FILE);
            return false;
        }

        // Make sure directory is writable
        if (!$ioObject->isWriteable($localFilePath)) {
            Mage::log("    BV - local directory '".$localFilePath."' is not writable.", Zend_Log::ERR, self::LOG_FILE);
            return false;
        }

        // Remove the local file if it already exists
        if (file_exists($localFilePath . DS . $localFileName)) {
            $ioObject->rm($localFilePath . DS . $localFileName);
        }
        
        try {
            $sftp = $this->connectSFTP($store);
            // Download the file
            $sftp->getFile($remoteFile, $localFilePath . DS . $localFileName);
        } catch (Exception $ex) {
            Mage::log('    BV - Exception downloading file: ' . $ex->getTraceAsString(), Zend_Log::ERR, self::LOG_FILE);
        }

        // Validate file was downloaded
        if (!$ioObject->fileExists($localFilePath . DS . $localFileName, true)) {
            Mage::log("    BV - unable to download file '" . $localFilePath . DS . $localFileName . "'", Zend_Log::ERR, self::LOG_FILE);
            return false;
        }

        return true;
    }


    public function uploadFile($localFileName, $remoteFile, $store)
    {
        Mage::log('    BV - starting upload to Bazaarvoice server', Zend_Log::INFO, self::LOG_FILE);
        
        try {
            $sftp = $this->connectSFTP($store);
            // Download the file
            $sftp->putAndDeleteFile($localFileName, $remoteFile);
        } catch (Exception $ex) {
            Mage::log('    BV - Exception uploading file: ' . $ex->getTraceAsString(), Zend_Log::ERR, self::LOG_FILE);
        }

        return true;
    }

    public function getSmartSEOContent($bvProduct, $bvSubjectArr, $pageFormat)
    {
        $ret = '';

        if (Mage::getStoreConfig('bazaarvoice/SmartSEOFeed/EnableSmartSEO') === '1') {
            $deploymentZone = $this->getDeploymentZoneForBVProduct($bvProduct);
            if ($pageFormat != '') {
                $pageFormat += '/';
            }

            $baseFolder = Mage::getBaseDir('var') . DS . 'import' . DS . 'bvfeeds' . DS . 'bvsmartseo' . DS;
            $smartSEOFile = $baseFolder . $deploymentZone . DS . $bvProduct . DS . $bvSubjectArr[$this->BV_SUBJECT_TYPE] . DS . '1' . DS . $pageFormat . $bvSubjectArr[$this->BV_EXTERNAL_SUBJECT_ID] . '.htm';

            if (isset($_REQUEST[$this->CONST_SMARTSEO_BVRRP])) {
                $smartSEOFile = $baseFolder . $_REQUEST[$this->CONST_SMARTSEO_BVRRP];
            } else if (isset($_REQUEST[$this->CONST_SMARTSEO_BVQAP])) {
                $smartSEOFile = $baseFolder . $_REQUEST[$this->CONST_SMARTSEO_BVQAP];
            } else if (isset($_REQUEST[$this->CONST_SMARTSEO_BVSYP])) {
                $smartSEOFile = $baseFolder . $_REQUEST[$this->CONST_SMARTSEO_BVSYP];
            }

            if (file_exists($smartSEOFile)) {
                $ret = file_get_contents($smartSEOFile);
            }

            if (!empty($ret)) {
                $helper = Mage::helper('core/url');
                $url = parse_url($helper->getCurrentUrl());
                
                $query = array();
                if (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] != '') {
                    foreach ($_GET as $key => $value) {
                        if ($key !== $this->CONST_SMARTSEO_BVRRP && $key !== $this->CONST_SMARTSEO_BVQAP && $key !== $this->CONST_SMARTSEO_BVSYP) {
                            $query[$helper->stripTags($key, null, true)] = $helper->stripTags($value, null, true);
                        }
                    }
                    $url['query'] = http_build_query($query);
                }
                
                $currentPage = $url['scheme'] . '://' . $url['host'] . $url['path'] . '?' . $url['query'];
                $ret = preg_replace("/\\{INSERT_PAGE_URI\\}/", $currentPage, $ret);
            }
        }

        return $ret;
    }
    
    /**
     * @static
     * @param  $userID
     * @param  $sharedkey
     * @return string
     */
    public function encryptReviewerId($userID)
    {
        $sharedKey = Mage::getStoreConfig('bazaarvoice/general/EncodingKey');
        $userStr = 'date=' . date('Ymd') . '&userid=' . $userID;
        return md5($sharedKey . $userStr) . bin2hex($userStr);
    }

    /**
     * @static
     * @param  $isStatic boolean indicating whether or not to return a URL to fetch static BV resources
     * @param  $bvProduct String indicating the BV product to get the URL for ('reviews', 'questions')
     * @return string
     */
    public function getBvUrl($isStatic, $bvProduct)
    {
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != '') ? 'https' : 'http';
        $hostSubdomain = $this->getSubDomainForBVProduct($bvProduct);
        $hostDomain = 'ugc.bazaarvoice.com';
        $bvStaging = $this->getBvStaging();
        $deploymentZone = $this->getDeploymentZoneForBVProduct($bvProduct);
        $stat = ($isStatic === 1) ? 'static/' : '';

        return $protocol . '://' . $hostSubdomain . '.' . $hostDomain . $bvStaging . $stat . $deploymentZone;
    }

    /**
     * Get url to bvapi.js javascript API file
     *
     * C2013 staging call:
     * ----------------------
     * <code>
     *   src="//display-stg.ugc.bazaarvoice.com/static/{{ClientName}}/{{DeploymentZoneName}}/{{Locale}}/bvapi.js"
     * </code>
     *
     * @static
     * @param  $isStatic
     * @return string
     */
    public function getBvApiHostUrl($isStatic, $store = null)
    {
        // Build protocol based on current page
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != '') ? 'https' : 'http';
        // Build hostname based on environment setting
        $environment = Mage::getStoreConfig('bazaarvoice/general/environment', $store);
        if ($environment == 'staging') {
            $apiHostname =  'display-stg.ugc.bazaarvoice.com';
        }
        else {
            $apiHostname =  'display.ugc.bazaarvoice.com';
        }
        // Build static dir name based on param
        if($isStatic) {
            $static = 'static/';
        }
        else {
            $static = '';
        }
        // Lookup other config settings
        $clientName = Mage::getStoreConfig('bazaarvoice/general/client_name', $store);
        $deploymnetZoneName = Mage::getStoreConfig('bazaarvoice/general/deployment_zone', $store);
        // Get locale code from BV config, 
        // Note that this doesn't use Magento's locale, this will allow clients to override this and map it as they see fit
        $localeCode = Mage::getStoreConfig('bazaarvoice/general/locale', $store);
        // Build url string
        $url = $protocol . '://' . $apiHostname . '/' . $static . $clientName . '/' . urlencode($deploymnetZoneName) . '/' . $localeCode;
        // Return final url
        return $url;
    }

    /**
     * @static
     * @return string Either returns '/' or '/bvstaging/'
     */
    public function getBvStaging()
    {
        $environment = Mage::getStoreConfig('bazaarvoice/general/environment');
        if ($environment == 'staging') {
            $bvStaging = '/bvstaging/';
        }
        else {
            $bvStaging = '/';
        }
        return $bvStaging;
    }

    public function getSFTPHost($store = null)
    {
        $environment = Mage::getStoreConfig('bazaarvoice/general/environment', $store);
        $ftpHostOverride = trim(Mage::getStoreConfig('bazaarvoice/bv_config/ftp_host_name', $store));
        if(strlen($ftpHostOverride)) {
            $sftpHost = $ftpHostOverride;
        }
        else if ($environment == 'staging') {
            $sftpHost = 'sftp-stg.bazaarvoice.com';
        }
        else {
            $sftpHost = 'sftp.bazaarvoice.com';
        }
        return $sftpHost;
    }

    /**
     * @static
     * @return string representing the default display code to be used across all available BV products
     */
    public function getDefaultDeploymentZone()
    {
        return Mage::getStoreConfig('bazaarvoice/general/deployment_zone');
    }

    /**
     * @static
     * @param  $bvProduct String indicating the BV product to get the displaycode for ('reviews', 'questions')
     * @return string
     */
    public function getDeploymentZoneForBVProduct($bvProduct)
    {
        return getDefaultDeploymentZone();
    }

    /**
     * @static
     * @param  $bvProduct String indicating the BV product to get the sub-domain for ('reviews', 'questions')
     * @return string
     */
    public function getSubDomainForBVProduct($bvProduct)
    {
        return $this->getConfigPropertyForBVProduct($bvProduct, 'SubDomain');
    }

    // =-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
    // =-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-

    public function getConfigPropertyForBVProduct($bvProduct, $propertyName)
    {
        $code = 'rr';
        if ($bvProduct === 'questions') {
            $code = 'qa';
        }

        return Mage::getStoreConfig('bazaarvoice/'.$code.'/'.$propertyName);
    }

    public function sendNotificationEmail($subject, $text)
    {
        $toEmail = Mage::getStoreConfig('bazaarvoice/feeds/admin_email');
        $fromEmail = Mage::getStoreConfig('trans_email/ident_general/email');   // The 'General' contact identity is a default setting in Magento
        if (empty($fromEmail)) {
            $fromEmail = $toEmail;
        }

        if (!empty($toEmail)) {
            /*
             * Loads the template file from
             *   app/locale/en_US/template/email/bazaarvoice_notification.html
             */
            $emailTemplate  = Mage::getModel('core/email_template')->loadDefault('bazaarvoice_notification_template');

            // Create an array of variables to assign to template
            $emailTemplateVariables = array();
            $emailTemplateVariables['text'] = $text;

            $emailTemplate->setSenderName('Bazaarvoice Magento Notifier');
            $emailTemplate->setSenderEmail($fromEmail);
            $emailTemplate->setTemplateSubject($subject);

            $emailTemplate->send($toEmail,'Bazaarvoice Admin', $emailTemplateVariables);
        }
    }
    
    /**
     * Returns the product unless the product visibility is
     * set to not visible.  In this case, it will try and pull
     * the parent/associated product from the order item.
     * 
     * @param Mage_Sales_Model_Order_Item $item
     * @return Mage_Catalog_Model_Product
     */
    public function getReviewableProductFromOrderItem($item)
    {
        $product = Mage::getModel('catalog/product');
        $product->setStoreId($item->getStoreId());
        $product->load($item->getProductId());
        if ($product->getVisibility() == Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE)
        {
            $options = $item->getProductOptions();
            if(isset($options['super_product_config']['product_id'])){
                try
                {
                    $parentId = $options['super_product_config']['product_id'];
                    $product = Mage::getModel('catalog/product')->load($parentId);
                }
                catch (Exception $ex) {}
            }
        }
        
        return $product;
    }
    
    /**
     *
     */
    public function getExtensionVersion()
    {
        return (string) Mage::getConfig()->getNode()->modules->Bazaarvoice_Connector->version;
    }
    
}
