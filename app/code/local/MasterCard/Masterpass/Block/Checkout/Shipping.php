<?php

class MasterCard_Masterpass_Block_Checkout_Shipping extends Mage_Checkout_Block_Onepage_Shipping {

    public function getAddress() {
        if (is_null($this->_address)) {
            $this->_address = Mage::helper('masterpass')-> returnAddress();
        }

        return $this->_address;
    }

}
