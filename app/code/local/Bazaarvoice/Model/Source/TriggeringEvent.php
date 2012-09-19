<?php
 /**
  * Source model for purchase feed triggering event
  */
class Bazaarvoice_Model_Source_TriggeringEvent {

    const PURCHASE = "purchase";
    const SHIPPING = "shipping";

    public function toOptionArray() {
        return array(
            array(
                'value' => self::SHIPPING,
                'label' => Mage::helper('Bazaarvoice')->__('Shipping')
            ),
            array(
                'value' => self::PURCHASE,
                'label' => Mage::helper('Bazaarvoice')->__('Purchase')
            )
        );
    }
}
