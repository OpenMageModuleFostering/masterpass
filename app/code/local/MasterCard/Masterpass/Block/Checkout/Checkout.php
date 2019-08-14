<?php

/*
 * Developer: Pavith Lovan
 */
/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

class MasterCard_Masterpass_Block_Checkout_Checkout extends Mage_Checkout_Block_Onepage_Abstract {
    
    public function _construct() {

        $this->setMasterpasscheckoutUrl(Mage::getUrl('', array('_secure' => true)) . "masterpass/index/placeorder/");

        parent::_construct();
    }

    public function getBilling() {
        return $this->getQuote()->getBillingAddress();
    }

    public function getShipping() {
        return $this->getQuote()->getShippingAddress();
    }

    public function getPaymentHtml() {
        return $this->getChildHtml('payment_info');
    }
    public function getAddress() {
        if (is_null($this->_address)) {
            if ($this->isCustomerLoggedIn()) {
                $this->_address = $this->getQuote()->getShippingAddress();
            } else {
                $ship = $this->getQuote()->getShippingAddress();
                $ship_country = $ship->getCountryId();
                if (!empty($ship_country))
                    $this->_address = $ship;
                else
                    $this->_address = Mage::getModel('sales/quote_address');
        }
        }

        return $this->_address;
    }
}
