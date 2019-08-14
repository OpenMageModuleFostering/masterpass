<?php

/*
 * Developer: Pavith Lovan
 */
/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

class MasterCard_Masterpass_Block_Checkout_Precheckout extends Mage_Checkout_Block_Cart_Totals {

    public $cards;
    public $address;
    public $reward;
    public $profile;
    public $longaccesstoken;
    public $walletname;
    public $precheckoutTransactionId;
    public $consumerWalletId;
    public $masterpassImage;
    public $partnerLogo;

    /**
     * @var Mage_Sales_Model_Quote
     */
    protected $_quote = null;

    /**
     * @var Mage_Sales_Model_Quote_Address
     */
    protected $_shippingAddress = null;

    /**
     * Currently selected shipping rate.
     *
     * @var Mage_Sales_Model_Quote_Address_Rate
     */
    protected $_currentShippingRate = null;

    public function _construct() {
        if (Mage::getStoreConfig('masterpass/config/userflows') == '2') {
            $this->setPostexpresscheckout("");
        } elseif (Mage::getStoreConfig('masterpass/config/userflows') == '3') {
            $this->setPostexpresscheckout(Mage::getUrl('', array('_secure' => true)) . "masterpass/masterpasspairing/placeorder");
        }
        parent::_construct();
    }

    public function getPrecheckoutdata() {
        $customer = Mage::getSingleton('customer/session')->getCustomer();
        $lonAccessToken = $customer->getData('longtoken');
        $result = false;
        if ($lonAccessToken) {
            try {

                $preCheckoutData = Mage::helper('masterpass')->postPreCheckoutData($lonAccessToken);

                if (!empty($preCheckoutData)) {

                    if (Mage::getStoreConfig('masterpass/config/cfcard')) {
                        $this->cards = $preCheckoutData->PrecheckoutData->Cards;
                        if (!$this->cards) {
                            //save long access token
                            Mage::helper('masterpass')->removeMpLongaccesstoken();
                        }
                    }
                    if (Mage::getStoreConfig('masterpass/config/cfprofile')) {
                        $this->profile = $preCheckoutData->PrecheckoutData->Contact;
                    }
                    if (Mage::getStoreConfig('masterpass/config/cfaddress')) {
                        $this->address = $preCheckoutData->PrecheckoutData->ShippingAddresses;
                    }
                    if (Mage::getStoreConfig('masterpass/config/exreward')) {
                        $this->reward = $preCheckoutData->PrecheckoutData->RewardPrograms;
                    }
                    $this->masterpassImage = $preCheckoutData->MasterpassLogoUrl;
                    $this->partnerLogo = $preCheckoutData->WalletPartnerLogoUrl;
                    // store partner logo for order confirmation page
                    Mage::getSingleton('core/session')->setPartnerLogo(htmlentities($preCheckoutData->WalletPartnerLogoUrl));

                    $this->longaccesstoken = $preCheckoutData->LongAccessToken;
                    $this->walletname = $preCheckoutData->PrecheckoutData->WalletName;
                    $this->precheckoutTransactionId = $preCheckoutData->PrecheckoutData->PrecheckoutTransactionId;
                    $this->consumerWalletId = $preCheckoutData->PrecheckoutData->ConsumerWalletId;

                    $result = true;
                } else {
                    return false;
                }
            } catch (Exception $e) {
                Mage::log($e->getMessage());
                //remove longaccesstoken
                Mage::helper('masterpass')->removeMpLongaccesstoken();

                Mage::getSingleton('core/session')->addError(Mage::getStoreConfig('masterpass/config/checkouterrormessage'));
                $domain = Mage::getUrl('', array(
                            '_secure' => true
                ));
                $url = $domain . "checkout/onepage/";
                Mage::app()->getFrontController()->getResponse()->setRedirect($url);
            }
        }
        return $result;
    }

    public function getLongToken() {
        return $this->longaccesstoken;
    }

    public function getPreCheckoutTranId() {
        return $this->precheckoutTransactionId;
    }

    public function getConWalletId() {
        return $this->consumerWalletId;
    }

    public function partnerLogo() {
        return $this->partnerLogo;
    }

    public function masterPassLogo() {
        return $this->masterpassImage;
    }

    public function getWalletName() {
        return $this->walletname;
    }

    public function connectedCallBackUrl() {
        return Mage::getUrl('', array('_secure' => true)) . "masterpass/masterpasspairing/connectedcheckout";
    }

    public function getPreCards() {
        if (!empty($this->cards)) {

            $cards = json_decode(json_encode((array) $this->cards), 1);
            if (is_array($cards) && !empty($cards['Card'][0])) {
                return $cards['Card'];
            } else {
                return $cards;
            }
        } else {
            return null;
        }
    }

    //get shipping address list
    public function getPreAddress() {
        //check if it's a virtual product
        $quote = $this->getQuote();

        if (!$quote->getIsVirtual()) {

            if (!empty($this->address)) {
                $address = json_decode(json_encode((array) $this->address), 1);
                Mage::getSingleton('customer/session')->setPreaddress($address);
                if (is_array($address) && !empty($address['ShippingAddress'][0])) {
                    return $address['ShippingAddress'];
                } else {
                    return $address;
                }
            } else {
                return null;
            }
        } else {
            return null;
        }
    }

    //set default shippping address
    public function mpShippingAddress() {
        $shipping = array();
        if (!empty($this->address)) {
            $address = json_decode(json_encode((array) $this->address), 1);

            if (is_array($address) && !empty($address['ShippingAddress'][0])) {
                $shipping = $address['ShippingAddress'][0];
            } else {
                $shipping = $address['ShippingAddress'];
            }
        }

        $shippingAdd = Mage::getSingleton('checkout/type_onepage');

        $name = explode(' ', $shipping['RecipientName']);
        if ($name[1] == '') {
            $name[1] = $name[0];
        }
        //check address line2 
        if (!is_array($shipping['Line2'])) {
            $lin2 = $shipping['Line2'];
        } else {
            $lin2 = '';
        }
        $mpData = Mage::getModel('masterpass/masterpass');

        // get shipping address

        $regionIdS = $this->checkMasterpassData($mpData->statesMapping($shipping['CountrySubdivision']));
        $regionS = $this->checkMasterpassData($mpData->getMasterpassRegion($shipping['CountrySubdivision']));
        $shippingPhone = $mpData->getRemoveDash($this->checkMasterpassData($shipping['RecipientPhoneNumber']));
        $data = array(
            'firstname' => $name[0],
            'lastname' => $name[1],
            'street' => array(0 => $shipping['Line1'], 1 => $lin2),
            'city' => $shipping['City'],
            'region_id' => $regionIdS,
            'region' => $regionS,
            'postcode' => $shipping['PostalCode'],
            'country_id' => $shipping['Country'],
            'telephone' => $shippingPhone
        );

        $shippingAdd->saveShipping($data, null);
    }

    public function getPreProfile() {
        if (!empty($this->profile)) {
            $profile = json_decode(json_encode((array) $this->profile), 1);
            if (is_array($profile)) {
                return $profile;
            } else {
                return $profile;
            }
        } else {
            return null;
        }
    }

    public function getPreReward() {
        if (!empty($this->reward)) {
            $reward = json_decode(json_encode((array) $this->reward), 1);
            if (is_array($reward) && !empty($reward['RewardProgram'][0])) {
                return $reward['RewardProgram'];
            } else {
                return $reward;
            }
        } else {
            return null;
        }
    }

    /**
     * Get checkout quote object.
     *
     * @return Mage_Sales_Model_Quote
     */
    public function getQuote() {
        if ($this->_quote === null) {
            $this->_quote = Mage::getSingleton("checkout/session")->getQuote();
        }
        return $this->_quote;
    }

    /**
     * Get shipping address from quote.
     * Returns null if the quote only contains virtual products.
     *
     * @return Mage_Sales_Model_Quote_Address
     */
    public function canbeShipped() {
        if ($this->getQuote()->isVirtual()) {
            return false;
        } else {
            return true;
        }
        // return $this->getQuote()->getShippingAddress();
    }

    public function acceptanceMark() {
        return '<img src="https://www.mastercard.com/mc_us/wallet/img/en/US/mp_mc_acc_' . Mage::getStoreConfig('masterpass/config/acceptancemark') . '_gif.gif" alt=" MasterPass Checkout"/>';
    }

    protected function _beforeToHtml() {


        $quote = $this->getQuote();

        if ($quote->getIsVirtual()) {
            $this->setShippingRateRequired(false);
        } else {
            $this->setShippingRateRequired(true);

            // misc shipping parameters
            $this->setShippingMethodSubmitUrl($this->getUrl("masterpass/masterpasspairing/saveShippingMethod"))
                    ->setCanEditShippingAddress(false) // MasterPass doesn't let the customer return to edit details at present.
                    ->setCanEditShippingMethod(true); // MasterPass doesn't support handling shipping methods at present, so this must always be true.
        }

        $this->setPlaceOrderUrl($this->getUrl("masterpass/masterpasspairing/placeOrder"));

        return parent::_beforeToHtml();
    }

    public function checkMasterpassData($mpData) {
        if (!is_array($mpData)) {
            $mpAfterCheck = $mpData;
        } else {
            $mpAfterCheck = '';
        }
        return $mpAfterCheck;
    }

}

?>
