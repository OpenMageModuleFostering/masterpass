<?php

class MasterCard_Masterpass_Block_Onepage_Link extends Mage_Checkout_Block_Onepage_Link {

    public function getCheckoutUrl() {
        //check if customer already paired wallet with masterpass
        
        if (!$this->helper('masterpass')->isMasterpassEnabled()) {
            return parent::getCheckoutUrl();
        }
        $masterpass_CheckoutUrl = Mage::helper('masterpass')->getMasterpassCheckoutUrl();

        if ($masterpass_CheckoutUrl!=null) {         
             return $this->getUrl($masterpass_CheckoutUrl, array('_secure' => true));
        }else{
            return parent::getCheckoutUrl();
        }
    }

}
