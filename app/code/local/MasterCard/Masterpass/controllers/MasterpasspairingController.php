<?php

class MasterCard_Masterpass_MasterpasspairingController extends Mage_Core_Controller_Front_Action {

    /**
     * Stores the customer's quote data object.
     * @var Mage_Sales_Model_Quote
     */
    protected $_quote = null;
    //make sure customer is login
    protected $_checkout = null;
    const APPROVAL_CODE = 'plMGCE';

    // if customer is not logged in redirect to loggin page
    public function preDispatch() {
        parent::preDispatch();

        if (!Mage::getSingleton('customer/session')->authenticate($this)) {
            $this->getResponse()->setRedirect(Mage::helper('customer')->getLoginUrl());
            $this->setFlag('', self::FLAG_NO_DISPATCH, true);
        }


        return $this;
    }

    public function getOnepage() {
        return Mage::getSingleton('checkout/type_onepage');
    }

    public function indexAction() {

        $this->loadLayout();

        $this->_title(Mage::helper('masterpass')->__('My MasterPass'))
                ->_title($methodTitle);

        $breadcrumbs = $this->getLayout()->getBlock('breadcrumbs');
        $breadcrumbs->addCrumb('masterpass', array('label' => Mage::helper('masterpass')->__('My MasterPass Pairing'), 'title' => Mage::helper('masterpass')->__('My MasterPass Pairing'), 'link' => Mage::getUrl('*/*')));

        $this->renderLayout();
    }

    public function precheckoutAction() {
        $getOnepage = $this->getOnepage();
        if ($this->_checkPk()) {
            $this->_redirect("checkout/cart");
            return $this;
        }
        if (!$getOnepage->getQuote()->hasItems()) {
            $this->_redirect("checkout/cart");
            return $this;
        }
        $this->_getQuote()->setMayEditShippingMethod(true)->save();

        $this->loadLayout();
        $this->getLayout()->getBlock('head')->setTitle($this->__('Checkout With MasterPass'));
        $this->renderLayout();
    }

    private function _checkPk() {
        //if private key path is empty redirect customer to shopping cart page
        $result = false;
        $private_key = Mage::helper('masterpass')->checkPrivateKey();
        if (!$private_key) {
            Mage::getSingleton('core/session')->addError(Mage::getStoreConfig('masterpass/config/checkouterrormessage'));
            //save long access token
            $customer = Mage::getSingleton('customer/session')->getCustomer();
            $customerData = Mage::getModel('customer/customer')->load($customer->getId());
            $longAccessToken = null;
            $customerData->setData('longtoken', $longAccessToken);

            $customerData->save();
            //$this->_redirect('checkout/cart');
            //$this->getResponse()->setRedirect(Mage::helper('customer')->getLoginUrl());
            $result = true;
        }
        return $result;
    }

    //get shipping methods
    public function availableshippingAction() {
        $country = (string) $this->getRequest()->getParam('country_id');
        $postcode = (string) $this->getRequest()->getParam('postcode');
        $city = (string) $this->getRequest()->getParam('city');
        $regionId = (string) $this->getRequest()->getParam('region_id');
        $region = (string) $this->getRequest()->getParam('region');

        $this->_getQuote()->getShippingAddress()
                ->setCountryId($country)
                ->setCity($city)
                ->setPostcode($postcode)
                ->setRegionId($regionId)
                ->setRegion($region)
                ->setCollectShippingRates(true);
        $this->_getQuote()->save();
        $this->loadLayout(false);
        $this->renderLayout();
    }

    // order summery
    public function reviewAction() {
        $this->loadLayout(false);
        $this->renderLayout();
    }

    /**
     * Shipping address save action
     */
    public function saveShippingAction() {

        if ($this->getRequest()->isPost()) {
            $preAddr = Mage::getSingleton('customer/session')->getPreaddress();

            if ($preAddr) {
                $data = array();
                if (!empty($preAddr['ShippingAddress'][0])) {
                    $preData = $preAddr['ShippingAddress'];
                } else {
                    $preData = $preAddr;
                }
                $dataId = $this->getRequest()->getPost('addressid');

                $mpData = Mage::getModel('masterpass/masterpass');
                foreach ($preData as $value) {
                    if ($dataId == $value['AddressId']) {
                        $reecipientName = $value['RecipientName'];
                        $name = Mage::helper('masterpass')->getRecipienName($reecipientName);

                        if (!is_array($value['Line2'])) {
                            $lin2 = $value['Line2'];
                        } else {
                            $lin2 = null;
                        }
                        $regionIdS = Mage::helper('masterpass')->checkMasterpassData($mpData->statesMapping($value['CountrySubdivision']));
                        $regionS = Mage::helper('masterpass')->checkMasterpassData($mpData->getMasterpassRegion($value['CountrySubdivision']));
                        $shippingPhone = $mpData->getRemoveDash(Mage::helper('masterpass')->checkMasterpassData($value['RecipientPhoneNumber']));
                        $data = array(
                            'firstname' => $name[0],
                            'lastname' => $name[1],
                            'street' => array(0 => $value['Line1'], 1 => $lin2),
                            'city' => $value['City'],
                            'region_id' => $regionIdS,
                            'region' => $regionS,
                            'postcode' => $value['PostalCode'],
                            'country_id' => $value['Country'],
                            'telephone' => $shippingPhone
                        );
                        break;
                    }
                }
                $customerAddressId = $this->getRequest()->getPost('shipping_address_id', false);
                $this->getOnepage()->saveShipping($data, $customerAddressId);
            }
        }
        $this->loadLayout(false);
        $this->renderLayout();
    }

    public function saveShippingMethodAction() {
        if ($this->getRequest()->isPost()) {
            $data = $this->getRequest()->getPost('shippingmethod', '');
            $result = $this->getOnepage()->saveShippingMethod($data);
            // $result will contain error data if shipping method is empty

            $this->getOnepage()->getQuote()->collectTotals()->save();
        }
        $this->loadLayout(false);
        $this->renderLayout();
    }

    public function pairingcallbackAction() {


        $pairing_token = $this->getRequest()->getParam('pairing_token');
        $pairing_verifier = $this->getRequest()->getParam('pairing_verifier');
        $preCheckoutData = null;
        if (!$pairing_token || !$pairing_verifier) {
            Mage::helper('masterpass')->resetMasterData();
            //$generic_error_message = Mage::getStoreConfig('masterpass/config/checkouterrormessage');
            //Mage::getSingleton('core/session')->addError($generic_error_message);
            $this->_redirect('masterpass/masterpasspairing/');
            return $this;
        }
        try {
            $longAccessToken = Mage::helper('masterpass')->getAccessToken($pairing_token, $pairing_verifier);
            if ($longAccessToken->accessToken) {

                //save long access token
                $customer = Mage::getSingleton('customer/session')->getCustomer();
                $customerData = Mage::getModel('customer/customer')->load($customer->getId());
                $customerData->setData('longtoken', $longAccessToken->accessToken);
                try {
                    $customerData->save();
                    $this->_redirect('masterpass/masterpasspairing/');
                } catch (Exception $e) {
                    Mage::getSingleton('core/session')->addError('There is a problem please try again later.');
                    $this->_redirect('masterpass/masterpasspairing/');
                }
            } else {
                $this->_redirect('masterpass/masterpasspairing/');
            }
        } catch (Exception $e) {
            Mage::helper('masterpass')->resetMasterData();
            $generic_error_message = Mage::getStoreConfig('masterpass/config/checkouterrormessage');
            Mage::getSingleton('core/session')->addError($generic_error_message);
            $this->_redirect('masterpass/masterpasspairing/');
        }
    }

    /**
     * Get checkout quote object.
     *
     * @return Mage_Sales_Model_Quote
     */
    protected function _getQuote() {
        if ($this->_quote === null) {
            $this->_quote = $this->_getCheckoutSession()->getQuote();
        }
        return $this->_quote;
    }

    /**
     * Get checkout session object.
     * @return Mage_Checkout_Model_Session
     */
    protected function _getCheckoutSession() {
        if ($this->_checkoutSession === null) {
            $this->_checkoutSession = Mage::getSingleton("checkout/session");
        }
        return $this->_checkoutSession;
    }

    public function placeOrderAction() {
        //check if private key path is empty
        if ($this->_checkPk()) {
            $this->_redirect("checkout/cart");
            return $this;
        }
        if ($this->getRequest()->isPost()) {
            $post = $this->getRequest()->getPost();
            $translate = Mage::getSingleton('core/translate');
            /* @var $translate Mage_Core_Model_Translate */
            $translate->setTranslateInline(false);
            try {
                $quote = $this->getQuote();
                $postObject = new Varien_Object();
                $postObject->setData($post);

                $error = false;

                if (!Zend_Validate::is(trim($post['longaccesstoken']), 'NotEmpty')) {
                    $error = true;
                }
                if (!Zend_Validate::is(trim($post['cards']), 'NotEmpty')) {
                    $error = true;
                }
                if (!$quote->getIsVirtual()) {

                    if (!Zend_Validate::is(trim($post['address']), 'NotEmpty') && !$post['shipping']) {
                        $error = true;
                    }
                }

                if ($error) {
                    throw new Exception();
                } else {
                    $precheckoutData = Mage::helper('masterpass')->postExpressCheckoutData($post);
                    $precheckoutData = json_decode(json_encode((array) $precheckoutData), 1);

                    if ($precheckoutData['Checkout']) {
                        $preData = $precheckoutData['Checkout'];

                        //send transaction back to masterpass
                        $postback_data = array(
                            'transaction_id' => $preData['TransactionId'],
                            'precheckout_id' => $preData['PreCheckoutTransactionId']
                        );
                        //get billing address and card 

                        $preData['Card']['BillingAddress'];

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
                            'firstname' => $preData['Contact']['FirstName'],
                            'lastname' => $preData['Contact']['FirstName'],
                            'street' => array(0 => $preData['Card']['BillingAddress']['Line1'], 1 => $line2),
                            'city' => $preData['Card']['BillingAddress']['City'],
                            'region_id' => $regionIdb,
                            'region' => $regionb,
                            'postcode' => $preData['Card']['BillingAddress']['PostalCode'],
                            'country_id' => $preData['Card']['BillingAddress']['Country'],
                            'telephone' => $bPhone
                        );
                        //save billing address
                        try {
                            $customerAddressId = $this->getRequest()->getPost('billing_address_id', false);
                            $this->getOnepage()->saveBilling($bdata, $customerAddressId);
                        } catch (Exception $e) {
                            Mage::getSingleton('core/session')->addError($e->getMessage());
                            $this->_redirect('masterpass/masterpasspairing/precheckout');
                        }

                        //if virtual product no shipping is required


                        if (!$quote->getIsVirtual()) {
                            if ($post['shipping']) {
                                $sdata = $post['shipping'];
                            } else {
                                $reecipientName = $preData['ShippingAddress']['RecipientName'];

                                $name = Mage::helper('masterpass')->getRecipienName($reecipientName);

                                if (!is_array($preData['ShippingAddress']['Line2'])) {
                                    $slin2 = $preData['ShippingAddress']['Line2'];
                                } else {
                                    $slin2 = null;
                                }
                                $regionIdS = Mage::helper('masterpass')->checkMasterpassData($mpData->statesMapping($preData['ShippingAddress']['CountrySubdivision']));
                                $regionS = Mage::helper('masterpass')->checkMasterpassData($mpData->getMasterpassRegion($preData['ShippingAddress']['CountrySubdivision']));
                                $shippingPhone = $mpData->getRemoveDash(Mage::helper('masterpass')->checkMasterpassData($preData['ShippingAddress']['RecipientPhoneNumber']));
                                $sdata = array(
                                    'firstname' => $name[0],
                                    'lastname' => $name[1],
                                    'street' => array(0 => $preData['ShippingAddress']['Line1'], 1 => $slin2),
                                    'city' => $preData['ShippingAddress']['City'],
                                    'region_id' => $regionIdS,
                                    'region' => $regionS,
                                    'postcode' => $preData['ShippingAddress']['PostalCode'],
                                    'country_id' => $preData['ShippingAddress']['Country'],
                                    'telephone' => $shippingPhone
                                );
                            }

                            try {
                                $this->getOnepage()->saveShipping($sdata, $customerAddressId);
                            } catch (Exception $e) {
                                Mage::getSingleton('core/session')->addError($e->getMessage());
                                $this->_redirect('masterpass/masterpasspairing/precheckout');
                            }
                        }
                        //save payment
                        $cdata = Mage::helper('masterpass')->getCardData($preData['Card']);

                        // $this->getOnepage()->savePayment($cdata);


                        try {
                            $this->getOnepage()->savePayment($cdata);
                        } catch (Mage_Core_Exception $e) {

                            Mage::getSingleton('core/session')->addError($e->getMessage());
                            $this->_redirect('masterpass/masterpasspairing/precheckout');
                        }

                        if ($cdata) {
                            $cdata['checks'] = Mage_Payment_Model_Method_Abstract::CHECK_USE_CHECKOUT | Mage_Payment_Model_Method_Abstract::CHECK_USE_FOR_COUNTRY | Mage_Payment_Model_Method_Abstract::CHECK_USE_FOR_CURRENCY | Mage_Payment_Model_Method_Abstract::CHECK_ORDER_TOTAL_MIN_MAX | Mage_Payment_Model_Method_Abstract::CHECK_ZERO_TOTAL;
                            $this->getOnepage()->getQuote()->getPayment()->importData($cdata);
                        }

                        $this->getOnepage()->saveOrder();


                        $this->getOnepage()->getQuote()->save();

                        $TransactionStatus = 'Success';
                        
                        Mage::helper('masterpass')->postTransaction($postback_data, $TransactionStatus, self::APPROVAL_CODE);
                        $this->_redirect('masterpass/index/success', array('_secure' => true));
                    } else {
                        if (isset($precheckoutData['Errors']['Error'])) {
                            $generic_error_message = preg_replace('/\d/', '', $precheckoutData['Errors']['Error']['Description']);
                        } else {
                            $generic_error_message = Mage::getStoreConfig('masterpass/config/checkouterrormessage');
                        }
                        //remove longaccesstoken
                        //save long access token
                        $customer = Mage::getSingleton('customer/session')->getCustomer();
                        $customerData = Mage::getModel('customer/customer')->load($customer->getId());
                        $longAccessToken = null;
                        $customerData->setData('longtoken', $longAccessToken);

                        $customerData->save();
                        $this->_redirect('masterpass/masterpasspairing/');

                        Mage::getSingleton('core/session')->addError(str_replace(':', '', $generic_error_message));
                        $this->_redirect('checkout/cart');
                    }
                }
            } catch (Mage_Core_Exception $e) {
                if (isset($postback_data)) {
                    $TransactionStatus = 'Failure';

                    Mage::helper('masterpass')->postTransaction($postback_data, $TransactionStatus, self::APPROVAL_CODE);
                }

                Mage::getSingleton('core/session')->addError($e->getMessage());
                $this->_redirect('masterpass/masterpasspairing/precheckout');
            }
        } else {
            $generic_error_message = Mage::getStoreConfig('masterpass/config/checkouterrormessage');
            Mage::getSingleton('core/session')->addError($generic_error_message);
            $this->_redirect('masterpass/masterpasspairing/precheckout');
        }
    }

    //connect checkout
    public function connectedcheckoutAction() {
        //check private key path is not empty
        if ($this->_checkPk()) {
            $this->_redirect("checkout/cart");
            return $this;
        }

        $requestToken = $this->getRequest()->getParam("oauth_token");
        $requestVerifier = $this->getRequest()->getParam("oauth_verifier");
        $checkoutResourceUrl = $this->getRequest()->getParam("checkout_resource_url");
        if (!$requestToken && !$requestVerifier) {
            $generic_error_message = Mage::getStoreConfig('masterpass/config/checkouterrormessage');
            Mage::getSingleton('core/session')->addError(str_replace(':', '', $generic_error_message));
            return $this->_redirect("checkout/cart");
            return $this;
        }

        $quote = $this->getQuote();
        try {
            $longAccessToken = Mage::helper('masterpass')->getAccessToken($requestToken, $requestVerifier);
            if ($longAccessToken->accessToken) {

                // get checkout data from masterpass
                $checkoutData = Mage::helper('masterpass')->getCheckoutData($checkoutResourceUrl, $longAccessToken->accessToken);

                $masterpassData = simplexml_load_string($checkoutData);
                $precheckoutData = json_decode(json_encode((array) $masterpassData), 1);

                if (!empty($precheckoutData)) {
                    $preData = $precheckoutData;
                    //send transaction back to masterpass
                    $postback_data = array(
                        'transaction_id' => $preData['TransactionId'],
                        'precheckout_id' => $preData['PreCheckoutTransactionId']
                    );

                    //get billing address and card 

                    $preData['Card']['BillingAddress'];

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
                        'firstname' => $preData['Contact']['FirstName'],
                        'lastname' => $preData['Contact']['FirstName'],
                        'street' => array(0 => $preData['Card']['BillingAddress']['Line1'], 1 => $line2),
                        'city' => $preData['Card']['BillingAddress']['City'],
                        'region_id' => $regionIdb,
                        'region' => $regionb,
                        'postcode' => $preData['Card']['BillingAddress']['PostalCode'],
                        'country_id' => $preData['Card']['BillingAddress']['Country'],
                        'telephone' => $bPhone,
                    );
                    //save billing address
                    try {
                        $customerAddressId = $this->getRequest()->getPost('billing_address_id', false);
                        $this->getOnepage()->saveBilling($bdata, $customerAddressId);
                    } catch (Exception $e) {
                        Mage::getSingleton('core/session')->addError($e->getMessage());
                        $this->_redirect('masterpass/masterpasspairing/precheckout');
                    }

                    //if virtual product no shipping is required


                    if (!$quote->getIsVirtual()) {
                        $reecipientName = $preData['ShippingAddress']['RecipientName'];

                        $name = Mage::helper('masterpass')->getRecipienName($reecipientName);

                        if (!is_array($preData['ShippingAddress']['Line2'])) {
                            $slin2 = $preData['ShippingAddress']['Line2'];
                        } else {
                            $slin2 = null;
                        }
                        $regionIdS = Mage::helper('masterpass')->checkMasterpassData($mpData->statesMapping($preData['ShippingAddress']['CountrySubdivision']));
                        $regionS = Mage::helper('masterpass')->checkMasterpassData($mpData->getMasterpassRegion($preData['ShippingAddress']['CountrySubdivision']));
                        $shippingPhone = $mpData->getRemoveDash(Mage::helper('masterpass')->checkMasterpassData($preData['ShippingAddress']['RecipientPhoneNumber']));
                        $sdata = array(
                            'firstname' => $name[0],
                            'lastname' => $name[1],
                            'street' => array(0 => $preData['ShippingAddress']['Line1'], 1 => $slin2),
                            'city' => $preData['ShippingAddress']['City'],
                            'region_id' => $regionIdS,
                            'region' => $regionS,
                            'postcode' => $preData['ShippingAddress']['PostalCode'],
                            'country_id' => $preData['ShippingAddress']['Country'],
                            'telephone' => $shippingPhone
                        );
                        try {
                            $customerAddressId = null;

                            $this->getOnepage()->saveShipping($sdata, $customerAddressId);
                        } catch (Exception $e) {
                            Mage::getSingleton('core/session')->addError($e->getMessage());
                            $this->_redirect('masterpass/masterpasspairing/precheckout');
                        }
                        // if connected checkout and address is not paired then auto select shipping method
                        if (!Mage::getStoreConfig('masterpass/config/cfaddress')) {
                            $address = $this->getOnepage()->getQuote()->getShippingAddress();

                            $rates = $address->collectShippingRates()
                                    ->getGroupedAllShippingRates();
                            asort($rates);
                            $i = 0;
                            $code = null;
                            foreach ($rates as $carrier) {
                                foreach ($carrier as $rate) {

                                    if ($i < 1) {
                                        $code = $rate->getData('code');
                                    }
                                    $i++;
                                }
                            }

                            try {

                                $this->getOnepage()->saveShippingMethod($code);
                                //$this->getOnepage()->getQuote()->collectTotals()->save();
                            } catch (Exception $e) {

                                Mage::getSingleton('core/session')->addError($e->getMessage());
                                $this->_redirect('masterpass/masterpasspairing/precheckout');
                            }
                        }
                        ////
                    }


                    //save payment
                    $cdata = Mage::helper('masterpass')->getCardData($preData['Card']);


                    try {
                        $this->getOnepage()->savePayment($cdata);
                    } catch (Mage_Core_Exception $e) {

                        Mage::getSingleton('core/session')->addError($e->getMessage());
                        $this->_redirect('masterpass/masterpasspairing/precheckout');
                    }

                    if ($cdata) {
                        $cdata['checks'] = Mage_Payment_Model_Method_Abstract::CHECK_USE_CHECKOUT | Mage_Payment_Model_Method_Abstract::CHECK_USE_FOR_COUNTRY | Mage_Payment_Model_Method_Abstract::CHECK_USE_FOR_CURRENCY | Mage_Payment_Model_Method_Abstract::CHECK_ORDER_TOTAL_MIN_MAX | Mage_Payment_Model_Method_Abstract::CHECK_ZERO_TOTAL;
                        $this->getOnepage()->getQuote()->getPayment()->importData($cdata);
                    }

                    $this->getOnepage()->saveOrder();

                    $this->getOnepage()->getQuote()->save();

                    $TransactionStatus = 'Success';
 
                    Mage::helper('masterpass')->postTransaction($postback_data, $TransactionStatus, self::APPROVAL_CODE);
                    $this->_redirect('masterpass/index/success', array('_secure' => true));
                } else {
                    $generic_error_message = Mage::getStoreConfig('masterpass/config/checkouterrormessage');
                    Mage::getSingleton('core/session')->addError($generic_error_message);
                    $this->_redirect('masterpass/masterpasspairing/precheckout');
                }
            } else {
                Mage::getSingleton('core/session')->addError('There is a problem please try again later.');
                $this->_redirect('masterpass/masterpasspairing/precheckout');
            }
        } catch (Exception $ex) {
            if (isset($postback_data)) {
                $TransactionStatus = 'Failure';

                Mage::helper('masterpass')->postTransaction($postback_data, $TransactionStatus, self::APPROVAL_CODE);
            }
            $generic_error_message = Mage::getStoreConfig('masterpass/config/checkouterrormessage');
            Mage::getSingleton('core/session')->addError($generic_error_message);
            $this->_redirect('masterpass/masterpasspairing/precheckout');
        }
    }

    public function getQuote() {
        if ($this->_quote === null) {
            $this->_quote = Mage::getSingleton("checkout/session")->getQuote();
        }
        return $this->_quote;
    }


}
