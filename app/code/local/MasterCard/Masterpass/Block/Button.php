<?php

/*
 * Developer: Pavith Lovan
 */

class MasterCard_Masterpass_Block_Button extends Mage_Core_Block_Template {

    //checkout type
    const EXPRESS_CHECKOUT = '3';
    const CONNECTED_CHECKOUT = '2';
    const STANDARD_CHECKOUT = '1';

    protected function _construct() {

        parent::_construct();
    }

    // checkout if user flow is set to express
    public function checkUserFlows() {
        if (Mage::helper('masterpass')->isMasterpassEnabled()) {
            if (Mage::getSingleton('customer/session')->isLoggedIn()) {
                if (Mage::getStoreConfig('masterpass/config/userflows') == self::CONNECTED_CHECKOUT || Mage::getStoreConfig('masterpass/config/userflows') == self::EXPRESS_CHECKOUT) {
                    return true;
                } elseif (Mage::getStoreConfig('masterpass/config/userflows') == self::STANDARD_CHECKOUT) {
                    return false;
                }
            } else {
                if ($this->getQuote()->isAllowedGuestCheckout()) {
                    return false;
                } else {
                    return true;
                }
            }
        } else {
            return false;
        }
    }

    public function walletIsPaired() {
        return Mage::helper('masterpass')->checkWallet();
    }

    public function getQuote() {
        return Mage::getSingleton('checkout/session')->getQuote();
    }

    //if digital product
    public function digitalItem() {
        return $this->getQuote()->isVirtual();
    }

}
