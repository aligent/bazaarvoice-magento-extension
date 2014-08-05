<?php

class Bazaarvoice_Connector_Model_Source_Attribute
{
    public function toOptionArray()
    {
        $attrib_data = array(
            array(
                'value' => '',
                'label' => 'Please Select...'
            )
        );
        
        $attributes = Mage::getResourceModel('catalog/product_attribute_collection')
            ->addFieldToFilter("is_user_defined", 1)
            ->getItems();
        
        foreach ($attributes as $attribute){
            $attrib_data[] = array(
                'value' => $attribute->getAttributeCode(),
                'label' => $attribute->getFrontendLabel()
            );
        }
        return $attrib_data;
    }
}
