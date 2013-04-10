<?php
/**
 * Event observer and indexer running application
 *
 * @author Bazaarvoice, Inc.
 */
class Bazaarvoice_Model_ExportProductFeed extends Mage_Core_Model_Abstract {

    protected function _construct() {}
    
    public function exportDailyProductFeed() {
        Mage::log("Start Bazaarvoice product feed generation");
        if(Mage::getStoreConfig("bazaarvoice/ProductFeed/EnableProductFeed") === "1") {
            /**
            *
            * process feed for the BazaarVoice. The feed will be FTPed to the BV FTP server
            *
            * Product & Catalog Feed to BV
            *
            * <?xml version="1.0" encoding="UTF-8"?>
            * <Feed xmlns="http://www.bazaarvoice.com/xs/PRR/ProductFeed/3.3"
            * 		  name="SiteName"
            * 		  incremental="false"
            *		  extractDate="2007-01-01T12:00:00.000000">
            *		<Categories>
            *			<Category>
            *				<ExternalId>1010</ExternalId>
            *				<Name>First Category</Name>
            *				<CategoryPageUrl>http://www.site.com/category.htm?cat=1010</CategoryPageUrl>
            *			</Category>
            *			..... 0-n categories
            *		</Categories>
            *		<Products>
            *			<Product>
            *				<ExternalId>2000001</ExternalId>
            *				<Name>First Product</Name>
            *				<Description>First Product Description Text</Description>
            *	TODO			<Brand>ProductBrand</Brand>
            *				<CategoryExternalId>1010</CategoryExternalId>
            *				<ProductPageUrl>http://www.site.com/product.htm?prod=2000001</ProductPageUrl>
            *				<ImageUrl>http://images.site.com/prodimages/2000001.gif</ImageUrl>
            *	TODO			<ManufacturerPartNumber>26-12345-8Z</ManufacturerPartNumber>
            *	TODO			<EAN>0213354752286</EAN>
            *			</Product>
            *			....... 0-n products
            *		</Products>
            *</Feed>
            */

            $productFeedFilePath = Mage::getBaseDir("var") . DS . 'export' . DS . 'bvfeeds';
            $productFeedFileName = 'productFeed-' . date('U') . '.xml';

            $ioObject = new Varien_Io_File();
            try {
                $ioObject->open(array('path'=>$productFeedFilePath));
            } catch (Exception $e) {
                $ioObject->mkdir($productFeedFilePath, 0777, true);
                $ioObject->open(array('path'=>$productFeedFilePath));
            }


            if($ioObject->streamOpen($productFeedFileName)) {

                $ioObject->streamWrite("<?xml version=\"1.0\" encoding=\"UTF-8\"?>".
                        "<Feed xmlns=\"http://www.bazaarvoice.com/xs/PRR/ProductFeed/5.2\"".
                        " generator=\"Magento Extension r5.1.4\"".
                        "  name=\"".Mage::getStoreConfig("bazaarvoice/General/CustomerName")."\"".
                        "  incremental=\"false\"".
                        "  extractDate=\"".date('Y-m-d')."T".date('H:i:s').".000000\">\n");


                Mage::log("    BV - processing all categories");
                $this->processCategories($ioObject);
                Mage::log("    BV - completed categories, beginning products");
                $this->processProducts($ioObject);
                Mage::log("    BV - completed processing all products");

                $ioObject->streamWrite("</Feed>\n");
                $ioObject->streamClose();

                $destinationFile = "/" . Mage::getStoreConfig("bazaarvoice/ProductFeed/ExportPath") . "/" . Mage::getStoreConfig("bazaarvoice/ProductFeed/ExportFileName");
                $sourceFile = $productFeedFilePath . DS . $productFeedFileName;
                $upload = Bazaarvoice_Helper_Data::uploadFile($sourceFile, $destinationFile);

                if (!$upload) {
                    Mage::log("    Bazaarvoice FTP upload failed! [filename = " . $productFeedFileName . "]");
                } else {
                    Mage::log("    Bazaarvoice FTP upload success! [filename = " . $productFeedFileName . "]");
                    $ioObject->rm($productFeedFileName);
                }
            }
        }
        Mage::log("End Bazaarvoice product feed generation");
    }

    private function processCategories($ioObject) {
        $categoryModel = Mage::getModel('catalog/category');
        $categoryIds = $categoryModel->getCollection();
        if(count($categoryIds) > 0) {
            $ioObject->streamWrite("<Categories>\n");
        }
        foreach ($categoryIds as $categoryId) {
            $category = $categoryModel->load($categoryId->getId()); //Load category object
            $categoryExternalId = Bazaarvoice_Helper_Data::getCategoryId($category);
            $categoryName = htmlspecialchars($category->getName(), ENT_QUOTES, "UTF-8");
            $categoryPageUrl = htmlspecialchars($category->getCategoryIdUrl(), ENT_QUOTES, "UTF-8");

            if (!$category->getIsActive() || empty($categoryExternalId) || is_null($categoryExternalId) || $category->getLevel() == 1) {
                Mage::log("        BV - Skipping category: " . $category->getUrlKey());
                continue;
            }

            $parentExtId = "";
            $parentCategory = Mage::getModel('catalog/category')->load($categoryId->getParentId());
            if (!is_null($parentCategory) && $parentCategory->getLevel() != 1) { //if parent category is the root category, then ignore it
                $parentExtId = "    <ParentExternalId>" . Bazaarvoice_Helper_Data::getCategoryId($parentCategory) . "</ParentExternalId>\n";
            }

            $ioObject->streamWrite("<Category>\n".
                         "    <ExternalId>".$categoryExternalId."</ExternalId>\n".
                         $parentExtId .
                         "    <Name>".$categoryName."</Name>\n".
                         "    <CategoryPageUrl>".$categoryPageUrl."</CategoryPageUrl>\n".
                         "</Category>\n");
        }
        if(count($categoryIds) > 0) {
            $ioObject->streamWrite("</Categories>\n");
        }
    }


    private function processProducts($ioObject) {
        $categoryModel = Mage::getModel('catalog/category');
        $productModel = Mage::getModel('catalog/product'); //Getting product model for access to product related functions
        $productIds = $productModel->getCollection(); //  *FROM MEMORY*  this should get all the products
        if(count($productIds) > 0) {
            $ioObject->streamWrite("<Products>\n");
        }
        foreach ($productIds as $productId) {
            // Reset product model to prevent model data persisting between loop iterations.
            $productModel->reset();
            $product = $productModel->load($productId->getId()); //Load product object
            $productExternalId = Bazaarvoice_Helper_Data::getProductId($product);
            $brand = htmlspecialchars($product->getAttributeText("manufacturer"));


            //A status of 1 means enabled, 0 means disabled
            if ($product->getStatus() != 1 || empty($productExternalId) || is_null($productExternalId)) {
                Mage::log("        BV - Skipping product: " . $product->getSku());
                continue;
            }


            $ioObject->streamWrite("<Product>\n".
                                   "    <ExternalId>".$productExternalId."</ExternalId>\n".
                                   "    <Name>".htmlspecialchars($product->getName(), ENT_QUOTES, "UTF-8")."</Name>\n".
                                   "    <Description>".htmlspecialchars($product->getShortDescription(), ENT_QUOTES, "UTF-8")."</Description>\n");

            if (!is_null($brand) && !empty($brand)) {
                $ioObject->streamWrite("    <Brand><ExternalId>" . $brand . "</ExternalId></Brand>\n");
            }
            $parentCategories = $productId->getCategoryIds();
            if (!is_null($parentCategories) && count($parentCategories) > 0 && !empty($parentCategories[0])) {
                $parentCategory = $categoryModel->load($parentCategories[0]);
                $categoryExternalId = Bazaarvoice_Helper_Data::getCategoryId($parentCategory);
                $ioObject->streamWrite("    <CategoryExternalId>" . $categoryExternalId . "</CategoryExternalId>\n");
            }
            $ioObject->streamWrite("    <ProductPageUrl>".$product->getProductUrl()."</ProductPageUrl>\n".
                                   "    <ImageUrl>".$product->getImageUrl()."</ImageUrl>\n".
                                   "</Product>\n");
        }
        if(count($productIds) > 0) {
            $ioObject->streamWrite("</Products>\n");
        }
    }

}