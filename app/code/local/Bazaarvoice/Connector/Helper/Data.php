<?php

class Bazaarvoice_Connector_Helper_Data extends Mage_Core_Helper_Abstract {

    const BV_SUBJECT_TYPE = "bvSubjectType";
    const BV_EXTERNAL_SUBJECT_NAME = "bvExternalSubjectName";
    const BV_EXTERNAL_SUBJECT_ID = "bvExternalSubjectID";

    const CONST_SMARTSEO_BVRRP = "bvrrp";
    const CONST_SMARTSEO_BVQAP = "bvqap";
    const CONST_SMARTSEO_BVSYP = "bvsyp";

    public function getInlineRatingsHtml($product, $pageContext) {
        if (!empty($product)) {
            $avgRating = $product->getBvAverageRating();
            $ratingRange = $product->getBvRatingRange();
            $reviewCount = $product->getBvReviewCount();

            if (is_numeric($avgRating) && is_numeric($ratingRange) && is_numeric($reviewCount)) {
                $avgRatingStr = preg_replace("/\./", "_", round($avgRating,1));
                if (strlen($avgRatingStr) == 1) {
                    $avgRatingStr .= "_0";
                }

                $starsFile = "rating-" . $avgRatingStr . ".gif";
            } else {
                $avgRating = "0.0";
                $ratingRange = "5";
                $reviewCount = "0";
                $starsFile = "rating-0_0.gif";
            }

            $ret = "<div id=\"BVInlineRatings\">";
            $ret .= "    <img src=\"".$pageContext->getSkinUrl('images/bazaarvoice/'.$starsFile) . "\" /> " . round($avgRating, 1) . " / " . round($ratingRange,0) . " (" . round($reviewCount,0) . ")";
            $ret .= "</div>";

            return $ret;
        }
        return "";

    }

    /**
     * Get the uniquely identifying product ID for a catalog product.
     *
     * This is the unique, product family-level id (duplicates are unacceptable).
     * If a product has its own page, this is its product ID. It is not necessarily
     * the SKU ID, as we do not collect separate Ratings & Reviews for different
     * styles of product - i.e. the "Blue" vs. "Red Widget".
     *
     * @static
     * @param  $product a reference to a catalog product object
     * @return The unique product ID to be used with Bazaarvoice
     */
    public function getProductId($product) {

        $rawProductId = $product->getSku();

        //>> Customizations go here
        //
        //<< No further customizations after this

        return self::replaceIllegalCharacters($rawProductId);

    }

    /**
     * Returns a product object that has the provided external ID.  This is a complementary
     * function to getProductId above.
     *
     * @static
     * @param  $productExternalId
     * @return product object for the provided external ID, or null if no match is found.
     */
    public function getProductFromProductExternalId($productExternalId) {
        $rawId = self::reconstructRawId($productExternalId);

        $model = Mage::getModel('catalog/product');

        $productCollection = $model->getCollection()->addAttributeToSelect('*')
                                                    ->addAttributeToFilter('sku', $rawId)
                                                    ->load();


        foreach ($productCollection as $product) {
            //return the first one
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
    public function getCategoryId($category) {

        $rawCategoryId = $category->getUrlKey();

        //>> Customizations go here
        //
        //<< No further customizations after this

        return self::replaceIllegalCharacters($rawCategoryId);

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
    public function replaceIllegalCharacters($rawId) {
        // We need to use a reversible replacement so that we can reconstruct the original ID later.
        //  Example rawId = qwerty$%@#asdf
        //  Example encoded = qwerty_bv36__bv37__bv64__bv35_asdf

        return preg_replace_callback('/[^\w\d\*-\._]/s', create_function('$match','return "_bv".ord($match[0])."_";'), $rawId);
    }

    public function reconstructRawId($externalId) {
        return preg_replace_callback('/_bv(\d*)_/s', create_function('$match','return chr($match[1]);'), $externalId);
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
    public function downloadFile($localFilePath, $localFileName, $remoteFile) {
        Mage::log("    BV - starting download from Bazaarvoice server");

        //Create the directory if it doesn't already exist.
        $ioObject = new Varien_Io_File();
        try {
            if (!$ioObject->fileExists($localFilePath, false)) {
                $ioObject->mkdir($localFilePath, 0777, true);
            }
        } catch (Exception $e) {
            //Most likely not enough permissions.
            Mage::log("    BV - failed attempting to create local directory '".$localFilePath."' to download feed.  Error trace follows: " . $e->getTraceAsString());
            return false;
        }

        //Make sure directory is writable
        if (!$ioObject->isWriteable($localFilePath)) {
            Mage::log("    BV - local directory '".$localFilePath."' is not writable.");
            return false;
        }

        //Establish a connection to the FTP host
        Mage::log("    BV - beginning file download");
        $connection = ftp_connect(Mage::getStoreConfig("bazaarvoice/General/FTPHost"));
        $login = ftp_login($connection, Mage::getStoreConfig("bazaarvoice/General/CustomerName"), Mage::getStoreConfig("bazaarvoice/General/FTPPassword"));
        ftp_pasv($connection, true);
        if (!$connection || !$login) {
            Mage::log("    BV - FTP connection attempt failed!");
            return false;
        }

        //Remove the local file if it already exists
        if (file_exists($localFilePath . DS . $localFileName)) {
            unlink($localFilePath . DS . $localFileName);
        }

        try {
            //Download the file
            ftp_get($connection, $localFilePath . DS . $localFileName, $remoteFile, FTP_BINARY);
        } catch (Exception $ex) {
            Mage::log("    BV - Exception downloading file: " . $ex->getTraceAsString());
        }

        //Validate file was downloaded
        if (!$ioObject->fileExists($localFilePath . DS . $localFileName, true)) {
            Mage::log("    BV - unable to download file '" . $localFilePath . DS . $localFileName . "'");
            return false;
        }

        return true;
    }


    public function uploadFile($localFileName, $remoteFile, $store) {
        Mage::log("    BV - starting upload to Bazaarvoice server");

        $connection = ftp_connect(Mage::getStoreConfig("bazaarvoice/General/FTPHost", $store->getId()));
        $login = ftp_login($connection, Mage::getStoreConfig("bazaarvoice/General/CustomerName", $store->getId()), Mage::getStoreConfig("bazaarvoice/General/FTPPassword", $store->getId()));
        ftp_pasv($connection, true);
        if (!$connection || !$login) {
            Mage::log("    BV - FTP connection attempt failed!");
            return false;
        }

        $upload = ftp_put($connection, $remoteFile, $localFileName, FTP_BINARY);

        ftp_close($connection);

        return $upload;
    }

    public function getExternalSubjectForPage($pageContext) {
        $ret = array();

        // empty() method usage below can only take a variable, not a function invocation, so we have to request
        // the product and category references early.

        // Getting the product/category reference from the registry doesn't make any extra DB calls since we're relying
        // upon the product/category template page to set this registry entry.  By default this is the case.
        //    See: http://fishpig.co.uk/the-magento-registry/
        $category = Mage::registry("current_category");
        $product = Mage::registry("product");

        if (!empty($product)) {
            $ret[self::BV_SUBJECT_TYPE] = "product";
            $ret[self::BV_EXTERNAL_SUBJECT_NAME] = $product->getName();
            $ret[self::BV_EXTERNAL_SUBJECT_ID] = self::getProductId($product);
        } else if (!empty($category)) {
            $ret[self::BV_SUBJECT_TYPE] = "category";
            $ret[self::BV_EXTERNAL_SUBJECT_NAME] = $category->getName();
            $ret[self::BV_EXTERNAL_SUBJECT_ID] = self::getCategoryId($category);
        }

        return $ret;
    }

    public function getSmartSEOContent($bvProduct, $bvSubjectArr, $pageFormat) {
        $ret = "";

        if(Mage::getStoreConfig("bazaarvoice/SmartSEOFeed/EnableSmartSEO") === "1") {
            $displayCode = self::getDisplayCodeForBVProduct($bvProduct);
            if ($pageFormat != "") {
                $pageFormat += "/";
            }

            $baseFolder = Mage::getBaseDir("var") . DS . "import" . DS . "bvfeeds" . DS . "bvsmartseo" . DS;
            $smartSEOFile = $baseFolder . $displayCode . DS . $bvProduct . DS . $bvSubjectArr[self::BV_SUBJECT_TYPE] . DS . "1" . DS . $pageFormat . $bvSubjectArr[self::BV_EXTERNAL_SUBJECT_ID] . ".htm";

            if (isset($_REQUEST[self::CONST_SMARTSEO_BVRRP])) {
                $smartSEOFile = $baseFolder . $_REQUEST[self::CONST_SMARTSEO_BVRRP];
            } else if (isset($_REQUEST[self::CONST_SMARTSEO_BVQAP])) {
                $smartSEOFile = $baseFolder . $_REQUEST[self::CONST_SMARTSEO_BVQAP];
            } else if (isset($_REQUEST[self::CONST_SMARTSEO_BVSYP])) {
                $smartSEOFile = $baseFolder . $_REQUEST[self::CONST_SMARTSEO_BVSYP];
            }

            if (file_exists($smartSEOFile)) {
                $ret = file_get_contents($smartSEOFile);
            }

            if (!empty($ret)) {
                $helper = Mage::helper("core/url");
                $url = parse_url($helper->getCurrentUrl());
                
                $query = array();
                if (isset($_SERVER["QUERY_STRING"]) && $_SERVER["QUERY_STRING"] != "") {
                    foreach ($_GET as $key => $value) {
                        if ($key !== self::CONST_SMARTSEO_BVRRP && $key !== self::CONST_SMARTSEO_BVQAP && $key !== self::CONST_SMARTSEO_BVSYP) {
                            $query[$helper->stripTags($key, null, true)] = $helper->stripTags($value, null, true);
                        }
                    }
                    $url["query"] = http_build_query($query);
                }
                
                $currentPage = $url["scheme"] . "://" . $url["host"] . $url["path"] . "?" . $url["query"];
                $ret = preg_replace("/\\{INSERT_PAGE_URI\\}/", $currentPage, $ret);
            }
        }

        return $ret;
    }
    
    /**
     * @static
     * @param  $userID
     * @return string
     */
    public function getActiveProfilesEditProfileLink($userID) {
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != "") ? "https" : "http";
        $hostSubdomain = Bazaarvoice_Connector_Helper_Data::getSubDomainForBVProduct("activeprofiles") . "/";
        $hostDomain = Mage::getStoreConfig("bazaarvoice/General/HostDomain");
        $bvStaging = Bazaarvoice_Connector_Helper_Data::getBvStaging();
        $bvDisplayCode = Bazaarvoice_Connector_Helper_Data::getDisplayCodeForBVProduct("activeprofiles");
        $bvUAS = Bazaarvoice_Connector_Helper_Data::encryptReviewerId($userID);

        return $protocol . "://" . $hostSubdomain . $hostDomain . $bvStaging . "profiles/" . $bvDisplayCode . "/editprofile.htm?user=" . $bvUAS;
    }

    /**
     * @static
     * @param  $userID
     * @param  $sharedkey
     * @return string
     */
    public function encryptReviewerId($userID) {
        $sharedKey = Mage::getStoreConfig("bazaarvoice/General/EncodingKey");
        $userStr = 'date=' . date("Ymd") . '&userid=' . $userID;
        return md5($sharedKey . $userStr) . bin2hex($userStr);
    }

    /**
     * @static
     * @param  $isStatic boolean indicating whether or not to return a URL to fetch static BV resources
     * @param  $bvProduct String indicating the BV product to get the URL for ("reviews", "questions", "stories", "activeprofiles")
     * @return string
     */
    public function getBvUrl($isStatic, $bvProduct) {
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != "") ? "https" : "http";
        $hostSubdomain = Bazaarvoice_Connector_Helper_Data::getSubDomainForBVProduct($bvProduct);
        $hostDomain = Mage::getStoreConfig("bazaarvoice/General/HostDomain");
        $bvStaging = Bazaarvoice_Connector_Helper_Data::getBvStaging();
        $bvDisplayCode = Bazaarvoice_Connector_Helper_Data::getDisplayCodeForBVProduct($bvProduct);
        $stat = ($isStatic === 1) ? "static/" : "";

        return $protocol . "://" . $hostSubdomain . "." . $hostDomain . $bvStaging . $stat . $bvDisplayCode;
    }

    /**
     * @static
     * @param  $isStatic
     * @return string
     */
    public function getBvApiHostUrl($isStatic) {
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != "") ? "https" : "http";
        $apiHostname = Mage::getStoreConfig("bazaarvoice/General/APIHostname");
        $bvStaging = Bazaarvoice_Connector_Helper_Data::getBvStaging();
        $bvDisplayCode = Bazaarvoice_Connector_Helper_Data::getDefaultDisplayCode();

        return $protocol . "://" . $apiHostname . $bvStaging . "static/" . $bvDisplayCode;
    }

    /**
     * @static
     * @return string Either returns "/" or "/bvstaging/"
     */
    public function getBvStaging() {
        $bvStaging = Mage::getStoreConfig("bazaarvoice/General/Staging");
        if ($bvStaging === "") {
            $bvStaging = "/";
        } else if ($bvStaging !== "/") {
            $bvStaging = "/bvstaging/";
        }
        return $bvStaging;
    }

    /**
     * @static
     * @return string representing the default display code to be used across all available BV products
     */
    public function getDefaultDisplayCode() {
        $dc = Bazaarvoice_Connector_Helper_Data::getDisplayCodeForBVProduct("reviews");
        if (empty($dc)) {
            $dc = Bazaarvoice_Connector_Helper_Data::getDisplayCodeForBVProduct("questions");
        }
        if (empty($dc)) {
            $dc = Bazaarvoice_Connector_Helper_Data::getDisplayCodeForBVProduct("stories");
        }
        if (empty($dc)) {
            $dc = Bazaarvoice_Connector_Helper_Data::getDisplayCodeForBVProduct("activeprofiles");
        }
        return $dc;
    }

    /**
     * @static
     * @param  $bvProduct String indicating the BV product to get the displaycode for ("reviews", "questions", "stories", "activeprofiles")
     * @return string
     */
    public function getDisplayCodeForBVProduct($bvProduct) {
        return Bazaarvoice_Connector_Helper_Data::getConfigPropertyForBVProduct($bvProduct, "DefaultDisplayCode");
    }

    /**
     * @static
     * @param  $bvProduct String indicating the BV product to get the sub-domain for ("reviews", "questions", "stories", "activeprofiles")
     * @return string
     */
    public function getSubDomainForBVProduct($bvProduct) {
        return Bazaarvoice_Connector_Helper_Data::getConfigPropertyForBVProduct($bvProduct, "SubDomain");
    }

    //=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
    //=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-

    public function getConfigPropertyForBVProduct($bvProduct, $propertyName) {
        $code = "RR";
        if ($bvProduct === "questions") {
            $code = "AA";
        } else if ($bvProduct === "stories") {
            $code = "SY";
        } else if ($bvProduct === "activeprofiles") {
            $code = "CP";
        }

        return Mage::getStoreConfig("bazaarvoice/".$code."/".$propertyName);
    }

    public function sendNotificationEmail($subject, $text) {
        $toEmail = Mage::getStoreConfig("bazaarvoice/General/AdminEmail");
        $fromEmail = Mage::getStoreConfig('trans_email/ident_general/email');   //The "General" contact identity is a default setting in Magento
        if (empty($fromEmail)) {
            $fromEmail = $toEmail;
        }

        if (!empty($toEmail)) {
            /*
             * Loads the template file from
             *   app/locale/en_US/template/email/bazaarvoice_notification.html
             */
            $emailTemplate  = Mage::getModel('core/email_template')->loadDefault('bazaarvoice_notification_template');

            //Create an array of variables to assign to template
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
    	$product = Mage::getModel("catalog/product")->load($item->getProductId());
    	if ($product->getVisibility() == Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE)
    	{
    		$options = $item->getProductOptions();
    		try
    		{
    			$parentId = $options["super_product_config"]["product_id"];
    			$product = Mage::getModel("catalog/product")->load($parentId);
    		}
    		catch (Exception $ex) {}
    	}
    	
    	return $product;
    }
}