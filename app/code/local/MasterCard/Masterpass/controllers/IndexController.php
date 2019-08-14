<?php

/*
 * Developer: Pavith Lovan
 */

class MasterCard_Masterpass_IndexController extends Mage_Core_Controller_Front_Action {
    /*
     * this method privides default action.
     */

    protected $mpshipping;
    protected $_quote = null;
    const APPROVAL_CODE = 'plMGCE';

    public function indexAction() {

        $this->_redirect('checkout/cart');
        return $this;
    }

    public function checkoutAction() {
        //if (!$this->getQuote()->isAllowedGuestCheckout()){
        //   $this->_redirect('checkout/onepage');
        //}
        if (!Mage::getSingleton('checkout/session')->getMasterPassCard()) {
            $this->_redirect('checkout/cart');
            return $this;
        }

        if ($this->getRequest()->isPost()) {
            $data = $this->getRequest()->getPost('shipping', array());
            $customerAddressId = $this->getRequest()->getPost('shipping_address_id', false);
            $this->getOnepage()->saveShipping($data, $customerAddressId);
        }

        $this->loadLayout();
        $this->_title(Mage::helper('masterpass')->__('Checkout With MasterPass'))
                ->_title('Checkout With MasterPass');
        $this->renderLayout();
    }

    public function callBackAction() {
        // if customer login and agree to pair
        if (Mage::getSingleton('customer/session')->isLoggedIn()) {
            $pairing_token = $this->getRequest()->getParam('pairing_token');
            $pairing_verifier = $this->getRequest()->getParam('pairing_verifier');
            //if customer allow pairing
            if ($pairing_token || $pairing_verifier) {
                $this->pairingcallback();
            }
        }
        $requestToken = $this->getRequest()->getParam("oauth_token");
        $requestVerifier = $this->getRequest()->getParam("oauth_verifier");
        $checkoutResourceUrl = $this->getRequest()->getParam("checkout_resource_url");

        if (!$requestToken || !$requestVerifier || !$checkoutResourceUrl) {
            Mage::getSingleton('core/session')->addError(Mage::getStoreConfig('masterpass/config/checkouterrormessage'));
            $this->_redirect('checkout/cart');
            return $this;
        }
        try {
            $accessTokenResponse = Mage::helper('masterpass')->getAccessToken($requestToken, $requestVerifier);
            if (!$accessTokenResponse) {
                Mage::getSingleton('core/session')->addError(Mage::getStoreConfig('masterpass/config/checkouterrormessage'));
                $this->_redirect('checkout/cart');
                return $this;
            }
            if ($accessTokenResponse->accessToken) {
                $checkoutData = Mage::helper('masterpass')->getCheckoutData($checkoutResourceUrl, $accessTokenResponse->accessToken);


                $masterpassData = simplexml_load_string($checkoutData);
                $preData = json_decode(json_encode((array) $masterpassData), 1);

                //if customer is not logged in set checkout method as a guest
                if (!Mage::getSingleton('customer/session')->isLoggedIn()) {
                    $checkout_method = 'guest';
                }
                $result = $this->getOnepage()->saveCheckoutMethod($checkout_method);

                //save Billing address
                $this->saveBilling($preData);
                // if order is not required shipping address
                if (!$this->getQuote()->getIsVirtual()) {  // required shipping
                    $this->saveShipping($preData['ShippingAddress']);
                }

                Mage::getSingleton('checkout/session')->setMasterPassCard($preData['Card']);
                $postback_data = array(
                    'transaction_id' => $preData['TransactionId'],
                    'precheckout_id' => $preData['PreCheckoutTransactionId']
                );
                // store postback_data to post transaction back to masterpass.
                Mage::getSingleton('checkout/session')->setPostBackData($postback_data);

                $cardMask = array(
                    'cardType' => $preData['Card']['BrandName'],
                    'cardHolder' => $preData['Card']['CardHolderName'],
                    'last4' => substr($preData['Card']['AccountNumber'], -4),
                    'expiryMonth' => $preData['Card']['ExpiryMonth'],
                    'expiryYear' => $preData['Card']['ExpiryYear']);
                if (isset($preData['RewardProgram'])) {
                    $cardMask['RewardProgram'] = $preData['RewardProgram'];
                }
                Mage::getSingleton('checkout/session')->setCardMask($cardMask);

                $this->_redirect('masterpass/index/checkout', array('_secure' => true));
            }
        } catch (Exception $ex) {
            Mage::getSingleton('core/session')->addError(Mage::getStoreConfig('masterpass/config/checkouterrormessage'));
            $this->_redirect('checkout/cart');
        }
    }

    // create order
    public function placeOrderAction() {
        try {
            $this->savePayment();

            $cdata = Mage::helper('masterpass')->getCardData($brandId = null);

            if ($cdata) {
                $cdata['checks'] = Mage_Payment_Model_Method_Abstract::CHECK_USE_CHECKOUT | Mage_Payment_Model_Method_Abstract::CHECK_USE_FOR_COUNTRY | Mage_Payment_Model_Method_Abstract::CHECK_USE_FOR_CURRENCY | Mage_Payment_Model_Method_Abstract::CHECK_ORDER_TOTAL_MIN_MAX | Mage_Payment_Model_Method_Abstract::CHECK_ZERO_TOTAL;
                $this->getOnepage()->getQuote()->getPayment()->importData($cdata);
            }

            $this->getOnepage()->saveOrder();
            $this->getOnepage()->getQuote()->save();
            
            $TransactionStatus = 'Success';
            Mage::helper('masterpass')->postTransaction(Mage::getSingleton('checkout/session')->getPostBackData(),$TransactionStatus,  self::APPROVAL_CODE);
            
            $this->_redirect('masterpass/index/success', array('_secure' => true));
        } catch (Exception $e) {
            $TransactionStatus = 'Failure';
            Mage::helper('masterpass')->postTransaction(Mage::getSingleton('checkout/session')->getPostBackData(),$TransactionStatus,self::APPROVAL_CODE);
            Mage::getSingleton('core/session')->addError($e->getMessage());
            $this->_redirect('masterpass/index/checkout', array('_secure' => true));
            return $this;
        }
    }

    // order summery
    public function reviewAction() {
        $this->loadLayout(false);
        $this->renderLayout();
    }

    public function saveShippingMethodAction() {
        if ($this->getRequest()->isPost()) {
            $data = $this->getRequest()->getPost('shippingmethod', '');
            $result = $this->getOnepage()->saveShippingMethod($data);
            // $result will contain error data if shipping method is empty

            $this->getOnepage()->getQuote()->collectTotals()->save();
            // $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
        }
        $this->loadLayout(false);
        $this->renderLayout();
    }

    protected function saveBilling($preData) {

        if (!is_array($preData['Card']['BillingAddress']['Line2'])) {
            $line2 = $preData['Card']['BillingAddress']['Line2'];
        } else {
            $line2 = '';
        }
        
        $mpData = Mage::getModel('masterpass/masterpass');
        $regionIdb = Mage::helper('masterpass')->checkMasterpassData($mpData->statesMapping($preData['Card']['BillingAddress']['CountrySubdivision']));
        $regionb = Mage::helper('masterpass')->checkMasterpassData($mpData->getMasterpassRegion($preData['Card']['BillingAddress']['CountrySubdivision']));
        $bPhone = $mpData->getRemoveDash(Mage::helper('masterpass')->checkMasterpassData($preData['Contact']['PhoneNumber']));


        $bdata = array(
            'firstname' => htmlentities($preData['Contact']['FirstName']),
            'lastname' => htmlentities($preData['Contact']['LastName']),
            'street' => array(0 => htmlentities($preData['Card']['BillingAddress']['Line1']), 1 => htmlentities($line2)),
            'city' => htmlentities($preData['Card']['BillingAddress']['City']),
            'region_id' => $regionIdb,
            'region' => htmlentities($regionb),
            'postcode' => $preData['Card']['BillingAddress']['PostalCode'],
            'country_id' => htmlentities($preData['Card']['BillingAddress']['Country']),
            'telephone' => $bPhone,
                //'use_for_shipping' => 1
        );

        if (!Mage::getSingleton('customer/session')->isLoggedIn()) {
            $bdata['email'] = trim($preData['Contact']['EmailAddress']);
        }

        try {
            $customerAddressId = null; //$this->getRequest()->getPost('billing_address_id', false);
            $this->getOnepage()->saveBilling($bdata, $customerAddressId);
        } catch (Exception $e) {
            Mage::getSingleton('core/session')->addError(Mage::getStoreConfig('masterpass/config/checkouterrormessage'));
            $this->_redirect('checkout/cart');
        }
    }

    protected function saveShipping($preData) {


        $reecipientName = $preData['RecipientName'];
        $name = Mage::helper('masterpass')->getRecipienName($reecipientName);

        if (!is_array($preData['Line2'])) {
            $lin2 = $preData['Line2'];
        } else {
            $lin2 = null;
        }

        $mpData = Mage::getModel('masterpass/masterpass');

        $regionIdS = Mage::helper('masterpass')->checkMasterpassData($mpData->statesMapping($preData['CountrySubdivision']));

        $regionS = Mage::helper('masterpass')->checkMasterpassData($mpData->getMasterpassRegion($preData['CountrySubdivision']));
        $shippingPhone = $mpData->getRemoveDash(Mage::helper('masterpass')->checkMasterpassData($preData['RecipientPhoneNumber']));
        $sdata = array(
            'firstname' => htmlentities($name[0]),
            'lastname' => htmlentities($name[1]),
            'street' => array(0 => htmlentities($preData['Line1']), 1 => htmlentities($lin2)),
            'city' => htmlentities($preData['City']),
            'region_id' => $regionIdS,
            'region' => htmlentities($regionS),
            'postcode' => $preData['PostalCode'],
            'country_id' => htmlentities($preData['Country']),
            'telephone' => $shippingPhone
        );

        //check if shippingsuppress is on
        if (Mage::getStoreConfig('masterpass/config/shippingsuppression') == 'true') {
            $reset_Shipping = Mage::getSingleton('checkout/type_onepage')->getQuote(); //->getShippingAddress();
            $sdata = array(
                'firstname' => null,
                'lastname' => null,
                'street' => null,
                'city' => null,
                'region_id' => null,
                'region' => null,
                'postcode' => null,
                'country_id' => null,
                'telephone' => null
            );
            $reset_Shipping->getShippingAddress()->setData($sdata);

            $reset_Shipping->getShippingAddress()->save();
            return true;
        }
        try {
            $customerAddressId = null;
            $this->getOnepage()->saveShipping($sdata, $customerAddressId);
            return true;
        } catch (Exception $e) {
            Mage::getSingleton('core/session')->addError(Mage::getStoreConfig('masterpass/config/checkouterrormessage'));
            $this->_redirect('checkout/cart');
        }
    }

    protected function savePayment() {
        //save payment

        $cdata = Mage::helper('masterpass')->getCardData($brandId = null);

        try {
            $this->getOnepage()->savePayment($cdata);
        } catch (Mage_Core_Exception $e) {
            Mage::getSingleton('core/session')->addError($e->getMessage());
            $this->_redirect('checkout/cart');
        }
    }

    protected function getOnepage() {
        return Mage::getSingleton('checkout/type_onepage');
    }

    protected function getQuote() {
        if ($this->_quote === null) {
            $this->_quote = Mage::getSingleton("checkout/session")->getQuote();
        }
        return $this->_quote;
    }

    protected function pairingcallback() {


        $pairing_token = $this->getRequest()->getParam('pairing_token');
        $pairing_verifier = $this->getRequest()->getParam('pairing_verifier');

        $preCheckoutData = null;
        if ($pairing_token || $pairing_verifier) {

            $longAccessToken = Mage::helper('masterpass')->getAccessToken($pairing_token, $pairing_verifier);
            if ($longAccessToken->accessToken) {

                //save long access token
                $customer = Mage::getSingleton('customer/session')->getCustomer();
                $customerData = Mage::getModel('customer/customer')->load($customer->getId());
                $customerData->setData('longtoken', $longAccessToken->accessToken);
                try {
                    $customerData->save();
                } catch (Exception $e) {
                    Mage::log($e->getMessage());
                }
            }
        }
    }

    protected function checkMasterpassData($mpData) {
        if (!is_array($mpData)) {
            $mpAfterCheck = $mpData;
        } else {
            $mpAfterCheck = '';
        }
        return $mpAfterCheck;
    }

    // express callback
    public function expresscallbackAction() {
        if (Mage::getSingleton('customer/session')->isLoggedIn()) {
            $pairing_token = $this->getRequest()->getParam('pairing_token');
            $pairing_verifier = $this->getRequest()->getParam('pairing_verifier');
            $preCheckoutData = null;
            if (!$pairing_token || !$pairing_verifier) {
                Mage::helper('masterpass')->resetMasterData();
                $generic_error_message = Mage::getStoreConfig('masterpass/config/checkouterrormessage');
                Mage::getSingleton('core/session')->addError($generic_error_message);
                $this->_redirect('checkout/cart');
            }
            try {
                $longAccessToken = Mage::helper('masterpass')->getAccessToken($pairing_token, $pairing_verifier);
                if ($longAccessToken->accessToken) {
                    //save long access token
                    $customer = Mage::getSingleton('customer/session')->getCustomer();
                    $customerData = Mage::getModel('customer/customer')->load($customer->getId());
                    $customerData->setData('longtoken', $longAccessToken->accessToken);
                    $customerData->save();


                    $this->_redirect('masterpass/masterpasspairing/precheckout', array('_secure' => true));
                } else {
                    $this->_redirect('checkout/cart');
                }
            } catch (Exception $e) {
                Mage::helper('masterpass')->resetMasterData();
                $generic_error_message = Mage::getStoreConfig('masterpass/config/checkouterrormessage');
                Mage::getSingleton('core/session')->addError($generic_error_message);
                $this->_redirect('checkout/cart');
            }
        } else {
            $this->_redirect('checkout/cart');
        }
    }


    //check if privatekey path is empty
    public function validatePkAction() {
        $result = false;
        if (Mage::helper('masterpass')->checkPrivateKey()) {
            $result = true;
        } else {
            Mage::getSingleton('core/session')->addError(Mage::getStoreConfig('masterpass/config/checkouterrormessage'));
            $result = false;
        }
        echo $result;
    }

    //order confirmation
    public function successAction() {

        $session = $this->getOnepage()->getCheckout();
        if (!$session->getLastSuccessQuoteId()) {
            $this->_redirect('checkout/cart');
            return;
        }

        $lastQuoteId = $session->getLastQuoteId();
        $lastOrderId = $session->getLastOrderId();
        $lastRecurringProfiles = $session->getLastRecurringProfileIds();
        if (!$lastQuoteId || (!$lastOrderId && empty($lastRecurringProfiles))) {
            $this->_redirect('checkout/cart');
            return;
        }

        $session->clear();
        $this->loadLayout();
        $this->_initLayoutMessages('checkout/session');
        Mage::dispatchEvent('checkout_onepage_controller_success_action', array('order_ids' => array($lastOrderId)));
        $this->renderLayout();
    }

}
