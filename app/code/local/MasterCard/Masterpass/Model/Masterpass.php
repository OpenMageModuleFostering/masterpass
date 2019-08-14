<?php

/*
 * Developer: Pavith Lovan
 */

class MasterCard_Masterpass_Model_Masterpass extends Mage_Core_Model_Abstract {

    public function __construct() {
        $this->_init('masterpass/masterpass');
        parent::__construct();
    }

    public function getCustomerWallet() {
        $masterPassData = Mage::getSingleton('checkout/session')->getMasterPassData();

        return $masterPassData;
    }

    public function getCreditCardDetail() {
        $arrayCard = array(
            '0' => '2323'
        );
        $xmlCard = $this->getCustomerWallet();
        $arrayCard = array(
            'BrandName' => (string) $xmlCard->Card->BrandName,
            'AccountNumber' => (string) $xmlCard->Card->AccountNumber,
            'CardHolderName' => (string) $xmlCard->Card->CardHolderName,
            'ExpiryMonth' => (string) $xmlCard->Card->ExpiryMonth,
            'ExpiryYear' => (string) $xmlCard->Card->ExpiryYear
        );
        $this->_masterPass = setBrandName((string) $xmlCard->Card->BrandName);
        $this->_masterPass = setAccountNumber((string) $xmlCard->Card->AccountNumber);
        $this->_masterPass = setCardHolderName((string) $xmlCard->Card->CardHolderName);
        $this->_masterPass = setExpiryMonth((string) $xmlCard->Card->ExpiryMonth);
        $this->_masterPass = setExpiryYear((string) $xmlCard->Card->ExpiryYear);
        return $this->_masterPass;
    }

    public function getBrandName() {
        $_Card = $this->getCustomerWallet();
        try {
            return (string) $_Card ['Card'] ['BrandName'];
        } catch (Exception $e) {
            return null;
        }
    }

    public function getAccountNumber() {
        $_Card = $this->getCustomerWallet();
        try {
            return (string) $_Card ['Card'] ['AccountNumber'];
        } catch (Exception $e) {
            return null;
        }
    }

    public function getCardHolderName() {
        $_Card = $this->getCustomerWallet();
        try {
            return (string) $_Card ['Card'] ['CardHolderName'];
        } catch (Exception $e) {
            return null;
        }
    }

    public function noExpirydate() {
        $_Card = $this->getCustomerWallet();
        if ($_Card ['Card'] ['ExpiryMonth'] == '') {
            return false;
        } else {
            return true;
        }
    }

    public function getExpiryMonth() {
        $_Card = $this->getCustomerWallet();

        try {
            if ($_Card ['Card'] ['ExpiryMonth'] == '') {
                $expiryMonth = '04';
            } else {
                $expiryMonth = $_Card ['Card'] ['ExpiryMonth'];
            }
            return (string) $expiryMonth;
        } catch (Exception $e) {
            return null;
        }
    }

    public function getExpiryYear() {
        $_Card = $this->getCustomerWallet();
        try {
            if ($_Card ['Card'] ['ExpiryYear'] == '') {
                $expiryYear = date('Y') + 4;
            } else {
                $expiryYear = $_Card ['Card'] ['ExpiryYear'];
            }
            return (string) $expiryYear;
        } catch (Exception $e) {
            return null;
        }
    }

    public function getAutho() {
        $autho = Mage::getSingleton('core/session');

        return $autho->getAutho();
    }

    public function getRemoveDash($string) {
        if (strstr($string, '-')) {
            $master = explode('-', $string);
            return $master [1];
        } else {
            return $string;
        }
    }

    public function statesMapping($stateCode) {

        $regionId = null;
        if (preg_match('/-/', $stateCode)) {
            $countryCode = explode('-', $stateCode);
            $stateCode = $this->getRemoveDash($stateCode);
            if ($countryCode [0]) { 
                $regionCollection = Mage::getModel('directory/region_api')->items($countryCode [0]);


                foreach ($regionCollection as $region) {
                    if ($stateCode == $region ['code']) {
                        $regionId = $region ['region_id'];
                    }
                }
            }
        } else {
            $regionId = '';
        }

        return $regionId;
    }

    //if not code try code 
    public function getMasterpassRegion($stateCode) {
        $region = null;
        if (preg_match('/-/', $stateCode)) {
            $region = '';
        } else {
            $region = $stateCode;
        }

        return $region;
    }

    /**
     * Update the shipping method set on the customer's quote.
     *
     * @param Mage_Sales_Model_Quote $quote
     * @param string $methodCode
     */
    public function updateShippingMethod(Mage_Sales_Model_Quote $quote, $methodCode) {
        Mage::dispatchEvent("mastercard_masterpass_update_shipping_method_before", array("quote" => $quote, "shipping_method" => $methodCode));

        $changed = false;
        if (!$quote->getIsVirtual() && $shippingAddress = $quote->getShippingAddress()) {
            if ($methodCode != $shippingAddress->getShippingMethod()) {
                $shippingAddress->setShippingMethod($methodCode)->setCollectShippingRates(true);
                $quote->collectTotals()->save();
                $changed = true;
            }
        }

        Mage::dispatchEvent("mastsercard_masterpass_update_shipping_method_after", array("quote" => $quote, "shipping_method" => $methodCode, "changed" => $changed));
    }

}

?>
