<?php
/**
 * @author Bazaarvoice, Inc.
 */
class Bazaarvoice_Connector_Model_RetrieveInlineRatingsFeed extends Mage_Core_Model_Abstract
{

    protected function _construct()
    {
    }

    public function retrieveInlineRatingsFeed()
    {
        Mage::log('Start Bazaarvoice Inline Ratings feed import');
        if (Mage::getStoreConfig('bazaarvoice/InlineRatingFeed/EnableInlineRatings') === '1') {
            $localFilePath = Mage::getBaseDir('var') . DS . 'import' . DS . 'bvfeeds';
            $localFileName = 'inline-ratings-' . date('U') . '.xml';
            $gzLocalFilename = $localFileName . '.gz';
            $remoteFile = '/' . Mage::getStoreConfig('bazaarvoice/InlineRatingFeed/FeedPath') . '/' . Mage::getStoreConfig('bazaarvoice/InlineRatingFeed/FeedFileName');

            if (!Mage::helper('bazaarvoice')->downloadFile($localFilePath, $gzLocalFilename, $remoteFile)) {
                // Unable to download the file.  Check magento log for messages.
                die('    BV - unable to process feed.  Check the Magento log for further information.');
            }

            // Unpack the file
            if (file_exists($localFilePath . DS . $localFileName)) {
                unlink($localFilePath . DS . $localFileName);
            }
            $gzInterface = new Mage_Archive_Gz();
            $gzInterface->unpack($localFilePath . DS . $gzLocalFilename, $localFilePath . DS . $localFileName);

            // Create custom product attributes
            $this->createProductAttributesIfNecessary();

            // Parse the XML
            $this->parseFeed($localFilePath . DS . $localFileName);

            // Cleanup
            if (file_exists($localFilePath . DS . $localFileName)) {
                unlink($localFilePath . DS . $localFileName);
            }
            if (file_exists($localFilePath . DS . $gzLocalFilename)) {
                unlink($localFilePath . DS . $gzLocalFilename);
            }
        }
        Mage::log('End Bazaarvoice Inline Ratings feed import');
    }


    private function parseFeed($fileName)
    {
        // Use XMLReader to parse the feed.  Should be available in all PHP5 environments, which is a pre-req of Magento
        // http://devzone.zend.com/article/2387

        $reader = new XMLReader();
        $reader->open($fileName);
        while ($reader->read()) {
            if (($reader->nodeType == XMLReader::ELEMENT)
                && ($reader->localName == 'Product')) {

                $this->processProduct($reader);

            }
        }

    }

    private function processProduct($xmlReader)
    {
        $endOfProduct = false;

        $bvProductExternalId = $xmlReader->getAttribute('id');
        $productAverageRating = 0;
        $productReviewCount = 0;
        $productRatingRange = 5;


        while (!$endOfProduct && $xmlReader->read()) {
            if ($xmlReader->nodeType == XMLReader::ELEMENT) {

                if ($xmlReader->localName == 'AverageOverallRating') {

                    $productAverageRating = $xmlReader->readString();

                } elseif ($xmlReader->localName == 'OverallRatingRange') {

                    $productRatingRange = $xmlReader->readString();

                } elseif ($xmlReader->localName == 'TotalReviewCount') {

                    $productReviewCount = $xmlReader->readString();

                }

            } elseif (($xmlReader->nodeType == XMLReader::END_ELEMENT)
                && ($xmlReader->localName == 'Product')) {
                $endOfProduct = true;
            }
        }

        // Persist data for this product
        $product = Mage::helper('bazaarvoice')->getProductFromProductExternalId($bvProductExternalId);
        if (!is_null($product)) {
            $product->setBvAverageRating($productAverageRating);
            $product->setBvReviewCount($productReviewCount);
            $product->setBvRatingRange($productRatingRange);
            $product->getResource()->saveAttribute($product, 'bv_average_rating');
            $product->getResource()->saveAttribute($product, 'bv_review_count');
            $product->getResource()->saveAttribute($product, 'bv_rating_range');

            Mage::log('    BV - InlineRating for product ' . $bvProductExternalId . ' = {' . $productAverageRating . ', ' . $productReviewCount . ', ' . $productRatingRange . '}');
        } else {
            Mage::log("    BV - Could not find product for ExternalID '" . $bvProductExternalId . "'");
        }
    }

    private function createProductAttributesIfNecessary()
    {
        $attributeModel = Mage::getModel('catalog/resource_eav_attribute');
        $entityTypeId = Mage::getModel('eav/entity')->setType('catalog_product')->getTypeId();

        $customAttributes = array('bv_average_rating', 'bv_review_count', 'bv_rating_range');

        foreach ($customAttributes as $customAttribute) {
            $attribute = $attributeModel->loadByCode($entityTypeId, $customAttribute);
            if (!$attribute->getId()) {
                $this->createProductAttribute($customAttribute);
            }
        }
    }

    private function createProductAttribute($attribCode)
    {

        $model = Mage::getModel('catalog/resource_eav_attribute');

        $data = array(
            'attribute_code' => $attribCode,
            'is_global' => '1',
            'frontend_input' => 'text', // equivalent of a text field
            'default_value_text' => '',
            'default_value_yesno' => '0', 
            'default_value_date' => '',
            'default_value_textarea' => '',
            'is_unique' => '0',
            'is_required' => '0',
            // 'apply_to' => array(),     // Apply to everything
            'is_configurable' => '0',
            'is_filterable' => '0',
            'is_filterable_in_search' => '0',
            'is_searchable' => '1',
            'is_visible_in_advanced_search' => '1',
            'is_comparable' => '0',
            'is_used_for_price_rules' => '0',
            'is_wysiwyg_enabled' => '0',
            'is_html_allowed_on_front' => '1',
            'is_visible_on_front' => '1',
            'used_in_product_listing' => '1',
            'used_for_sort_by' => '0',
            'frontend_label' => array($attribCode)
        );

        $data['backend_type'] = 'decimal'; // $model->getBackendTypeByInput($data['frontend_input']);

        $model->addData($data);
        $model->setEntityTypeId(Mage::getModel('eav/entity')->setType('catalog_product')->getTypeId());
        $model->setIsUserDefined(1);
        try {
            $model->save();
        } catch (Exception $e) {
            
        }
    }
    
}

