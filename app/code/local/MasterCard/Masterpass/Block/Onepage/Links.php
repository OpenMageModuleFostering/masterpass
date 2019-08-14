<?php

class MasterCard_Masterpass_Block_Onepage_Links extends Mage_Checkout_Block_Onepage_Link {

    public function getCheckoutUrl() {
        return $this->getUrl('checkout/onepage', array('_secure' => true));
    }

    /**
     * Add link on checkout page to parent block
     *
     * @return Mage_Checkout_Block_Links
     */
    public function addCheckoutLink() {
       
        return $this;
    }

}
