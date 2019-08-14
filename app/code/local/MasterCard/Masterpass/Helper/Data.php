<?php

/*
 * Developer: Pavith Lovan
 */

class MasterCard_Masterpass_Helper_Data extends Mage_Core_Helper_Abstract {

    public $service;
    public $appData;
    public $xmlVersion;

    //checkout type
    const EXPRESS_CHECKOUT = '3';
    const CONNECTED_CHECKOUT = '2';
    //card types
    const DINERS = 'diners';
    const MASTER = 'master';
    const VISA = 'visa';
    const MAESTRO = 'maestro';
    const DISCOVER = 'discover';
    const AMEX = 'amex';
    const OTHER = 'OT'; // other type can be use for private label card

    public function __construct() {

        // masterpass api version
        $this->xmlVersion = Mage::getStoreConfig('masterpass/config/masterpassxml');

        $this->service = Mage::getModel('masterpass/mpservice_masterpassservice');
    }

    // check if MasterPass is enabled
    public function isMasterpassEnabled() {
        $checkPv_key = $this->checkPrivateKey();

        if (Mage::getStoreConfig('masterpass/config/enabled') && $checkPv_key) {
            return true;
        } else {
            return false;
        }
    }

    public function isExpressEnabled() {
        if (Mage::getStoreConfig('masterpass/config/userflows') == self::CONNECTED_CHECKOUT || Mage::getStoreConfig('masterpass/config/userflows') == self::EXPRESS_CHECKOUT) {
            return true;
        } else {
            return false;
        }
    }

    //get recipien name
    public function getRecipienName($reecipientName) {
        $name = explode(' ', $reecipientName);
        if ($name[1] == '') {
            $name[1] = $name[0];
        }
        return $name;
    }

    public function checkIfSensitiveRequest(Mage_Core_Controller_Request_Http $request) {

        if ($request->getRequestedRouteName() == "masterpass") {
            return false;
        } else {
            return true;
        }
    }
    //check masterpass wallet status
    public function checkWallet(){
        $customer = Mage::getSingleton('customer/session')->getCustomer();
        $customerData = Mage::getModel('customer/customer')->load($customer->getId());
        $longtoken = $customerData->getData('longtoken');

        // check if wallet is unpaired
        if ($longtoken) {
            $preCheckoutData = $this->postPreCheckoutData($longtoken);

            if (empty($preCheckoutData)) {
                $this->removeMpLongaccesstoken();
                $longtoken = null;
            }
        }
        if (!empty($longtoken)) {
            return true;
        } else {
            return false;
        }
    }
    public function checkMasterpassData($mpData) {
        if (!is_array($mpData)) {
            $mpAfterCheck = $mpData;
        } else {
            $mpAfterCheck = '';
        }
        return $mpAfterCheck;
    }
    //return address
    public function returnAddress() {
        $_address = null;
        if ($this->isCustomerLoggedIn()) {
            $_address = $this->getQuote()->getShippingAddress();
        } else {
            $ship = $this->getQuote()->getShippingAddress();
            $ship_country = $ship->getCountryId();
            if (!empty($ship_country))
                $_address = $ship;
            else
                $_address = Mage::getModel('sales/quote_address');
        }

        return $_address;
    }

    // make sure all session is unset
    public function resetMasterData() {

        Mage::getSingleton('core/session')->unsProfileName();
        Mage::getSingleton('core/session')->unsPrecheckout();
        Mage::getSingleton('checkout/session')->unsMasterPassCard();
        Mage::getSingleton('checkout/session')->unsPostBackData();
        Mage::getSingleton('checkout/session')->unsCardMask();
        Mage::getSingleton('customer/session')->unsPreaddress();
    }

    //validate privatekey password
    public function checkPrivateKey() {
        $keystore = Mage::getModel('masterpass/mpservice_connector')->merchantPrivateKey();

        if ($keystore['pkey']) {
            return true;
        } else {
            Mage::log('Invalid private key password');
            return false;
        }
    }

    //remove existing long eccesstokem
    public function removeMpLongaccesstoken() {
        $customer = Mage::getSingleton('customer/session')->getCustomer();
        $customerData = Mage::getModel('customer/customer')->load($customer->getId());
        $longAccessToken = null;
        $customerData->setData('longtoken', $longAccessToken);

        $customerData->save();
        return $this;
    }

    // request masterpass token

    public function getMasterpassToken() {
        $module_path = Mage::getModuleDir('', 'MasterCard_Masterpass');

        $prefix = 'https://';
        if (Mage::getStoreConfig('masterpass/config/environment') === 'sandbox') {
            $prefix .= Mage::getStoreConfig('masterpass/config/environment') . ".";
            $consumerKey = Mage::helper('core')->decrypt(Mage::getStoreConfig('masterpass/config/sbconsumerkey'));
        } else {
            $consumerKey = Mage::helper('core')->decrypt(Mage::getStoreConfig('masterpass/config/prconsumerkey'));
        }

        $requestUrl = $prefix . "api.mastercard.com/oauth/consumer/v1/request_token";
        $callbackUrl = Mage::getUrl('', array('_secure' => true)) . "masterpass/index/callback";
        try {
            $requestToken = $this->service->GetRequestToken($requestUrl, $callbackUrl);

            return $requestToken;
        } catch (Exception $e) {
            Mage::log($e->getMessage());

            return $requestToken = null;
        }
    }

    /* get magento shopping */

    public function getShoppingCart() {

        $cart = Mage::getSingleton('checkout/session')->getQuote();
        // Number of items
        $numberOfItems = $cart->getItemsCount();
        $requestToken = null;

        $xml_cart = '';
        if ($numberOfItems > 0 && isset($this->getMasterpassToken()->requestToken)) {
            $requestToken = $this->getMasterpassToken()->requestToken;

            $xml_cart = "<?xml version=\"1.0\"?><ShoppingCartRequest><OAuthToken>" . $requestToken . "</OAuthToken><ShoppingCart>";

            $currencyCode = $cart->getGlobalCurrencyCode();

            $xml_cart .= "<CurrencyCode>" . $currencyCode . "</CurrencyCode>";
            $subTotal = $cart->getGrandTotal();
            $subTotal = $subTotal * 100;
            $xml_cart .= "<Subtotal>" . $subTotal . "</Subtotal>";

            $all_items = $cart->getAllVisibleItems();
            $generic_error_message = Mage::getStoreConfig('masterpass/config/checkouterrormessage');

            foreach ($all_items as $item) {
                $xml_cart .= "<ShoppingCartItem>";
                $productId = $item->getProductId();
                $productPrice = $item->getPrice();
                $description = htmlentities($item->getName());
                $value = $item->getPrice();

                $quantity = $item->getQty();
                $value = $value * $quantity;
                $value = $value * 100;
                $imageUrl = htmlentities(Mage::helper('catalog/image')->init($item->getProduct(), 'thumbnail'));
                $xml_cart .= "<Description>" . $description . "</Description><Quantity>" . $quantity . "</Quantity><Value>" . $value . "</Value><ImageURL>" . $imageUrl . "</ImageURL>";
                $xml_cart .= "</ShoppingCartItem>";
            }
            // if coupon
            $discount = $cart->getTotals();
            if (isset($discount['discount'])) {

                $discountAmount = $discount['discount']->getValue() * 100;
                $discountAmount = $discountAmount; //*-1;
                if (isset($discountAmount)) {
                    $xml_cart .= '<ShoppingCartItem><Description>Discount</Description><Quantity>1</Quantity><Value>' . $discountAmount . '</Value><ImageURL></ImageURL></ShoppingCartItem>';
                }
            }
            $xml_cart .= "</ShoppingCart><OriginUrl>" . Mage::getBaseUrl() . "</OriginUrl></ShoppingCartRequest>";

            return $xml_cart;
        } else {
            return $requestToken;
        }
    }

    // post shopping cart to MasterPass
    public function postShoppingCart() {

        $shoppingCartRequest = $this->getShoppingCart();

        if (!empty($shoppingCartRequest)) {
            $prefix = 'https://';
            if (Mage::getStoreConfig('masterpass/config/environment') === 'sandbox') {
                $prefix .= Mage::getStoreConfig('masterpass/config/environment') . ".";
                $consumerKey = Mage::helper('core')->decrypt(Mage::getStoreConfig('masterpass/config/sbconsumerkey'));
            }
            $shoppingCartUrl = $prefix . "api.mastercard.com/masterpass/" . $this->xmlVersion . "/shopping-cart";

            try {
                $service = Mage::getModel('masterpass/mpservice_masterpassservice');
                $shoppingCartResponse = $service->postShoppingCartData($shoppingCartUrl, $shoppingCartRequest);
                $data = simplexml_load_string($shoppingCartResponse);
                $data = json_decode(json_encode((array) $data), 1);
                return $data['OAuthToken'];
            } catch (Exception $e) {

                Mage::log($e->getMessage());
                return null;
            }
        } else {
            return null;
        }
    }

    public function acceptedCards() {
        $acceptedCards = Mage::getStoreConfig('masterpass/config/acceptedcards');
        $privateLabel = Mage::getStoreConfig('masterpass/config/privatelabel');
        if ($privateLabel) {
            $acceptedCards = $acceptedCards . ',' . $privateLabel;
        }
        return $acceptedCards;
    }

    public function getMerchantSettings() {
        if (Mage::getStoreConfig('masterpass/config/requestbasiccheckout') == 'true') {
            $basicchk = '1';
        } else {
            $basicchk = '';
        }
        $data = array('checkout_id' => '',
            'suppressShipping' => '',
            'loyaltyEnabled' => Mage::getStoreConfig('masterpass/config/rewards'),
            'loyaltyProgram' => Mage::getStoreConfig('masterpass/config/loyaltyprogram'),
            'requestBasicCheckout' => $basicchk,
            'version' => Mage::getStoreConfig('masterpass/config/masterpassxml'));

        if (Mage::getStoreConfig('masterpass/config/environment') === 'sandbox') {
            $data['checkout_id'] = Mage::helper('core')->decrypt(Mage::getStoreConfig('masterpass/config/sbcheckoutidentifier'));
        } else {
            $data['checkout_id'] = Mage::helper('core')->decrypt(Mage::getStoreConfig('masterpass/config/prcheckoutidentifier'));
        }
        if ($this->getQuote()->isVirtual()) {
            $data['suppressShipping'] = 'true';
        } else {
            if (Mage::getStoreConfig('masterpass/config/shippingsuppression') == 'true') {
                $data['suppressShipping'] = 'true';
            } else {
                $data['suppressShipping'] = 'false';
            }
        }
        $data['loyaltyEnabled'] = Mage::getStoreConfig('masterpass/config/rewards');
        return $data;
    }

    public function getQuote() {
        return Mage::getSingleton('checkout/session')->getQuote();
    }

    // get access token from masterpass
    public function getAccessToken($requestToken, $requestVerifier) {


        $prefix = 'https://';
        if (Mage::getStoreConfig('masterpass/config/environment') === 'sandbox') {
            $prefix .= Mage::getStoreConfig('masterpass/config/environment') . ".";
        }
        $accessUrl = $prefix . "api.mastercard.com/oauth/consumer/v1/access_token";

        try {
            $getAccessToken = $this->service->GetAccessToken($accessUrl, $requestToken, $requestVerifier);
            return $getAccessToken;
        } catch (Exception $e) {
            Mage::log($e->getMessage());
            return null;
        }
    }

    public function getCheckoutData($checkoutResourceUrl, $accessToken) {
        $checkoutObject = $this->service->GetPaymentShippingResource($checkoutResourceUrl, $accessToken);

        return $checkoutObject;
    }

    /**
     *
     * Used to format the Errors XML for display
     *
     * @return formatted error message
     */
    // Used to format the Error XML for display
    public function formatError($errorMessage) {
        if (preg_match(Connector::ERRORS_TAG, $errorMessage) > 0) {
            $errorMessage = $this->formatXML($errorMessage);
        }
        return $errorMessage;
    }

    // formate xml
    public function formatXML($resources) {
        if ($resources != null) {
            $dom = new DOMDocument;
            $dom->preserveWhiteSpace = FALSE;
            $dom->loadXML($resources);
            $dom->formatOutput = TRUE;
            $resources = $dom->saveXml();

            $resources = htmlentities($resources);
        }
        return $resources;
    }

    //******************************EXPRESS CHECKOUT ********************************************
    // express call back url
    public function expresscallBackurl() {
        return Mage::getUrl('', array('_secure' => true)) . "masterpass/index/expresscallback";
    }

    public function postMerchantInitData() {
        $pairingToken = $this->getMasterpassToken()->requestToken;
        if ($pairingToken) {
            $merchantInitRequest = $this->parseMerchantInitXML($pairingToken);

            $prefix = 'https://';
            if (Mage::getStoreConfig('masterpass/config/environment') === 'sandbox') {
                $prefix .= Mage::getStoreConfig('masterpass/config/environment') . ".";
            }
            $merchantInitUrl = $prefix . "api.mastercard.com/masterpass/" . $this->xmlVersion . "/merchant-initialization";

            $merchantInitResponse = $this->service->postMerchantInitData($merchantInitUrl, $merchantInitRequest);
            $merchantInitResponse = simplexml_load_string($merchantInitResponse);
            $merchantInitResponse = json_decode(json_encode((array) $merchantInitResponse), 1);
            return $merchantInitResponse['OAuthToken'];
        } else {
            return false;
        }
    }

    public function parseMerchantInitXML($pairingToken) {

        $originUrl = Mage::getUrl('', array('_secure' => true));
        $merchantInitData = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><MerchantInitializationRequest><OAuthToken>' . $pairingToken . '</OAuthToken><PreCheckoutTransactionId/><OriginUrl>' . $originUrl . '</OriginUrl></MerchantInitializationRequest>';

        return $merchantInitData;
    }

    //masterpass checkout url
    public function getMasterpassCheckoutUrl() {
        //check if customer already paired wallet with masterpass
        $customer = Mage::getSingleton('customer/session')->getCustomer();
        $lonAccessToken = $customer->getData('longtoken');

        $url = null;
        if (!empty($lonAccessToken) && Mage::getStoreConfig('masterpass/config/userflows') == self::CONNECTED_CHECKOUT) {
            $url = 'masterpass/masterpasspairing/precheckout';
        } elseif (!empty($lonAccessToken) && Mage::getStoreConfig('masterpass/config/userflows') == self::EXPRESS_CHECKOUT) {
            $url = 'masterpass/masterpasspairing/precheckout';
        }
        return $url;
    }

    public function pairingDataTypes() {
        $pairingDataType = '';  //["CARD,PROFILE,ADDRESS,REWARD_PROGRAM"],
        if (Mage::getStoreConfig('masterpass/config/cfcard')) {
            $pairingDataType .= 'CARD,';
        }
        if (Mage::getStoreConfig('masterpass/config/cfprofile')) {
            $pairingDataType .= 'PROFILE,';
        }
        if (Mage::getStoreConfig('masterpass/config/cfaddress')) {
            $pairingDataType .= 'ADDRESS,';
        }
        if (Mage::getStoreConfig('masterpass/config/exreward')) {
            $pairingDataType .= 'REWARD_PROGRAM,';
        }
        $pairingDataType = substr($pairingDataType, 0, -1);
        return $pairingDataType;
    }

    public function requestExpressCheckout() {
        $xpr = 'false';
        if (Mage::getStoreConfig('masterpass/config/userflows') == self::EXPRESS_CHECKOUT) {
            $xpr = 'true';
        }
        return $xpr;
    }

    public function postPreCheckoutData($longAccessToken) {
        $preCheckoutRequest = $this->parsePrecheckoutXml();


        $prefix = 'https://';
        if (Mage::getStoreConfig('masterpass/config/environment') === 'sandbox') {
            $prefix .= Mage::getStoreConfig('masterpass/config/environment') . ".";
        }
        $preCheckoutUrl = $prefix . "api.mastercard.com/masterpass/" . $this->xmlVersion . "/precheckout";

        try {
            $preCheckoutResponse = $this->service->getPreCheckoutData($preCheckoutUrl, $preCheckoutRequest, $longAccessToken);

            // Special syntax for working with SimpleXMLElement objects
            $preCheckoutResponse = simplexml_load_string($preCheckoutResponse);
            if ($preCheckoutResponse != null) {

                //$preCheckoutResponse->LongAccessToken;
                $this->saveLongToken($preCheckoutResponse->LongAccessToken);
                return $preCheckoutResponse;
            }
        } catch (Exception $e) {
            Mage::log($e->getMessage());
            return null;
        }
    }

    protected function saveLongToken($longToken) {
        //save long access token
        $customer = Mage::getSingleton('customer/session')->getCustomer();
        $customerData = Mage::getModel('customer/customer')->load($customer->getId());
        $customerData->setData('longtoken', $longToken);
        try {
            $customerData->save();
        } catch (Exception $e) {
            Mage::log($e->getMessage());
        }
    }

    public function postExpressCheckoutData($post) {
        $response = null;
        $longAccessToken = $post['longaccesstoken'];
        $prefix = 'https://';
        if (Mage::getStoreConfig('masterpass/config/environment') === 'sandbox') {
            $prefix .= Mage::getStoreConfig('masterpass/config/environment') . ".";
            $checkout_id = Mage::helper('core')->decrypt(Mage::getStoreConfig('masterpass/config/sbcheckoutidentifier'));
        } else {
            $checkout_id = Mage::helper('core')->decrypt(Mage::getStoreConfig('masterpass/config/prcheckoutidentifier'));
        }
        $expressCheckoutUrl = $prefix . "api.mastercard.com/masterpass/" . $this->xmlVersion . "/expresscheckout";

        $cart = Mage::getSingleton('checkout/session')->getQuote();
        $subTotal = $cart->getGrandTotal();
        $subTotal = $subTotal * 100;

        //check if noshipping required
        $noshipping = false;
        $quote = Mage::getSingleton("checkout/session")->getQuote();
        if ($quote->isVirtual()) {
            $noshipping = true;
        } elseif (!$quote->isVirtual() && !Mage::getStoreConfig('masterpass/config/cfaddress')) {
            $noshipping = true;
        }
        $expressCheckoutRequest = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<ExpressCheckoutRequest>
    <MerchantCheckoutId>' . $checkout_id . '</MerchantCheckoutId>
    <PrecheckoutTransactionId>' . $post['precheckoutid'] . '</PrecheckoutTransactionId>
    <CurrencyCode>' . Mage::app()->getStore($storeID)->getCurrentCurrencyCode() . '</CurrencyCode>
    <OrderAmount>' . $subTotal . '</OrderAmount>
    <CardId>' . $post['cards'] . '</CardId>';
        if ($noshipping) {
            $expressCheckoutRequest .= '<DigitalGoods>' . $noshipping . '</DigitalGoods>';
        } else {
            $expressCheckoutRequest .= '<ShippingAddressId>' . $post['address'] . '</ShippingAddressId>';
        }
        if (isset($post['rewardprogram'])) {
            $expressCheckoutRequest .= '<RewardProgramId>' . $post['rewardprogram'] . '</RewardProgramId>';
        }
        $expressCheckoutRequest .= '<WalletId>' . $post['walletid'] . '</WalletId>
    <AdvancedCheckoutOverride>false</AdvancedCheckoutOverride>
    <OriginUrl>' . Mage::getBaseUrl() . '</OriginUrl>
</ExpressCheckoutRequest>';


        try {
            $expressCheckoutResponse = $this->service->getExpressCheckoutData($expressCheckoutUrl, $expressCheckoutRequest, $longAccessToken);

            $response = simplexml_load_string($expressCheckoutResponse);

            $this->saveLongToken($response->LongAccessToken);
            return $response;
        } catch (Exception $e) {
            Mage::log($e->getMessage());
            return;
        }
    }

    public function parsePrecheckoutXml() {
        $typesXml = "";
        $pairingData = $this->pairingDataTypes();
        $pairingData = explode(',', $pairingData);

        foreach ($pairingData as $dataType) {
            $typesXml = $typesXml . sprintf("<PairingDataType><Type>%s</Type></PairingDataType>", $dataType);
        }
        $preCheckoutRequest = simplexml_load_string(sprintf("<PrecheckoutDataRequest><PairingDataTypes>%s</PairingDataTypes></PrecheckoutDataRequest>", $typesXml));

        return $preCheckoutRequest->asXML();
    }

    // post transaction back to masterpass
    public function postTransaction($post, $TransactionStatus, $ApprovalCode) {


        $prefix = 'https://';
        if (Mage::getStoreConfig('masterpass/config/environment') === 'sandbox') {
            $prefix .= Mage::getStoreConfig('masterpass/config/environment') . ".";
        }
        $postbackUrl = $prefix . "api.mastercard.com/masterpass/" . $this->xmlVersion . "/transaction";

        $sentXml = '';
        try {


            $consumerKey = $this->service->getConsumerKey();

            $currency = Mage::app()->getStore()->getCurrentCurrencyCode();

            $nowString = date(DATE_ATOM);

            $orderReference = Mage::getSingleton('checkout/session')->getLastRealOrderId();
            $lastOrderId = Mage::getSingleton('checkout/session')->getLastOrderId();
            $order = Mage::getSingleton('sales/order');
            $order->load($lastOrderId);
            $_totalData = $order->getData();
            $subTotal = $_totalData['grand_total'];

            $subTotal = str_replace(',', '', $subTotal);
            $orderAmount = $subTotal * 100;

            //if expree checkout
            if (Mage::getStoreConfig('masterpass/config/userflows') == self::EXPRESS_CHECKOUT) {
                $expresschk = "true";
            } else {
                $expresschk = "false";
            }

            $sentXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><MerchantTransactions><MerchantTransactions><TransactionId>' . $post['transaction_id'] . '</TransactionId><ConsumerKey>' . $consumerKey . '</ConsumerKey><Currency>' . $currency . '</Currency><OrderAmount>' . $orderAmount . '</OrderAmount><PurchaseDate>' . $nowString . '</PurchaseDate><TransactionStatus>' . $TransactionStatus . '</TransactionStatus><ApprovalCode>' . $ApprovalCode . '</ApprovalCode><PreCheckoutTransactionId>' . $post['precheckout_id'] . '</PreCheckoutTransactionId>
		<ExpressCheckoutIndicator>' . $expresschk . '</ExpressCheckoutIndicator></MerchantTransactions></MerchantTransactions>';
            $resources = $this->service->PostCheckoutTransaction($postbackUrl, $sentXml);

            Mage::getSingleton('checkout/session')->setPostBackMp(true);
        } catch (Exception $e) {

            Mage::log($e->getMessage());
            Mage::getSingleton('checkout/session')->setPostBackMp(false);
            return;
        }

        return $this->postback;
    }

    public function ifPostback() {
        return Mage::getSingleton('checkout/session')->getPostBackMp();
    }

    public function getCardData($brandId) {


        $cardType = null;
        $cdata = null;
        if ($brandId == null) {
            $preData = Mage::getSingleton('checkout/session')->getMasterPassCard();
        } else {
            $preData = $brandId;
        }
        if ($preData['BrandId'] == self::DINERS) {
            $cardType = 'OT';
        } elseif ($preData['BrandId'] == self::MASTER) {
            $cardType = 'MC';
        } elseif ($preData['BrandId'] == self::VISA) {
            $cardType = 'VI';
        } elseif ($preData['BrandId'] == self::MAESTRO) {
            $cardType = 'SM';
        } elseif ($preData['BrandId'] == self::DISCOVER) {
            $cardType = 'DI';
        } elseif ($preData['BrandId'] == self::AMEX) {
            $cardType = 'AE';
        } else {
            $cardType = 'OT';
        }

        $nameOnCard = $preData['CardHolderName'];
        $ccNum = $preData['AccountNumber'];
        $expMonth = $preData['ExpiryMonth'];
        $expYear = $preData['ExpiryYear'];

        $cdata = array(
            'method' => Mage::getStoreConfig('masterpass/config/paymentgateway'),
            'cc_owner' => $nameOnCard,
            'cc_type' => $cardType,
            'cc_number' => $ccNum,
            'cc_number_enc' => Mage::getSingleton('payment/info')->encrypt($ccNum),
            'cc_last4' => substr($ccNum, -4),
            'cc_exp_month' => $expMonth,
            'cc_exp_year' => $expYear
        );
        return $cdata;
    }

}
