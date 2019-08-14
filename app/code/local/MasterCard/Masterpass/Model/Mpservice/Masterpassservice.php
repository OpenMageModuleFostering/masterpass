<?php

class MasterCard_Masterpass_Model_Mpservice_Masterpassservice extends MasterCard_Masterpass_Model_Mpservice_Connector {

    //Request Token Response
    const XOAUTH_REQUEST_AUTH_URL = "xoauth_request_auth_url";
    const OAUTH_CALLBACK_CONFIRMED = "oauth_callback_confirmed";
    const OAUTH_EXPIRES_IN = "oauth_expires_in";
    //Request Token Response
    const OAUTH_TOKEN_SECRET = "oauth_token_secret";
    const ORIGIN_URL = "origin_url";
    // Callback URL parameters
    const OAUTH_TOKEN = "oauth_token";
    const OAUTH_VERIFIER = "oauth_verifier";
    const CHECKOUT_RESOURCE_URL = "checkout_resource_url";
    const REDIRECT_URL = "redirect_url";
    const PAIRING_TOKEN = "pairing_token";
    const PAIRING_VERIFIER = "pairing_verifier";
    // Redirect Parameters
    const CHECKOUT_IDENTIFIER = 'checkout_identifier';
    const ACCEPTABLE_CARDS = 'acceptable_cards';
    const OAUTH_VERSION = 'oauth_version';
    const VERSION = 'version';
    const SUPPRESS_SHIPPING_ADDRESS = 'suppress_shipping_address';
    const ACCEPT_REWARDS_PROGRAM = 'accept_reward_program';
    const SHIPPING_LOCATION_PROFILE = 'shipping_location_profile';
    const WALLET_SELECTOR = 'wallet_selector_bypass';
    const DEFAULT_XMLVERSION = "v1";
    const AUTH_LEVEL = "auth_level";
    const BASIC = "basic";
    const XML_VERSION_REGEX = "/v[0-9]+/";
    const REALM_TYPE = "eWallet";
    const APPROVAL_CODE = "sample";
    const POST = "POST";
    const GET = "GET";
    const OAUTH_BODY_HASH = "oauth_body_hash";
    const AMP = "&";
    const EQUALS = "=";

    public $originUrl;
    public $requestToken;
    public $verifier;
    public $checkoutResourceUrl;
    public $accessToken;
    public $paymentShippingResource;
    public $oAuthSecret;
    public $accessTokenCallAuthHeader;
    public $accessTokenCallSignatureBaseString;
    //public $requestToken;
    public $authorizeUrl;
    public $callbackConfirmed;
    public $oAuthExpiresIn;
    //public $oAuthSecret;
    public $redirectUrl;

    public function __construct() {
        $this->_init('masterpass/mpservice_masterpassservice');
        $consumerKey = $this->getConsumerKey();


        $originUrl = Mage::getUrl('', array(
                    '_secure' => true
        ));

        parent::__construct($consumerKey);
        $this->originUrl = $originUrl;
    }

    public function getConsumerKey() {

        if (Mage::getStoreConfig('masterpass/config/environment') === 'sandbox') {
            $consumerKey = Mage::helper('core')->decrypt(Mage::getStoreConfig('masterpass/config/sbconsumerkey'));
        } else {
            $consumerKey = Mage::helper('core')->decrypt(Mage::getStoreConfig('masterpass/config/prconsumerkey'));
        }
        return $consumerKey;
    }

    /**
     * SDK:
     * This method captures the Checkout Resource URL and Request Token Verifier
     * and uses these to request the Access Token.
     * @param $requestToken
     * @param $verifier
     * @return Output is Access Token
     */
    public function GetAccessToken($accessUrl, $requestToken, $verifier) {

        $params = array(
            self::OAUTH_VERIFIER => $verifier,
            self::OAUTH_TOKEN => $requestToken
        );

        $return = ''; //new AccessTokenResponse();
        $response = $this->doRequest($params, $accessUrl, POST, null);
        $responseObject = $this->parseConnectionResponse($response);

        $return->accessToken = isset($responseObject[self::OAUTH_TOKEN]) ? $responseObject[self::OAUTH_TOKEN] : "";
        $return->oAuthSecret = isset($responseObject[self::OAUTH_TOKEN]) ? $responseObject[self::OAUTH_TOKEN_SECRET] : "";
        return $return;
    }

    /**
     * SDK:
     * This method gets a request token and constructs the redirect URL
     * @param $requestUrl
     * @param $callbackUrl
     * @param $acceptableCards
     * @param $checkoutProjectId
     * @param $xmlVersion
     * @param $shippingSuppression
     * @param $rewardsProgram
     * @param $authLevelBasic
     * @param $shippingLocationProfile
     * @param $walletSelector
     * @return Output is a RequestTokenResponse object containing all data returned from this method
     */
    public function getRequestTokenAndRedirectUrl($requestUrl, $callbackUrl, $acceptableCards, $checkoutProjectId, $xmlVersion, $shippingSuppression, $rewardsProgram, $authLevelBasic, $shippingLocationProfile, $walletSelector) {

        $return = $this->getRequestToken($requestUrl, $callbackUrl);
        $return->redirectURL = $this->getConsumerSignInUrl($acceptableCards, $checkoutProjectId, $xmlVersion, $shippingSuppression, $rewardsProgram, $authLevelBasic, $shippingLocationProfile, $walletSelector);
        return $return;
    }

    /**
     * Method used to parse the connection response and return a array of the data
     *
     * @param $responseString
     *
     * @return Array with all response parameters
     */
    public function parseConnectionResponse($responseString) {

        $token = array();
        foreach (explode(self::AMP, $responseString) as $p) {
            @list($name, $value) = explode(self::EQUALS, $p, 2);
            $token[$name] = urldecode($value);
        }
        return $token;
    }

    /**
     * SDK:
     * This method posts the Shopping Cart data to MasterCard services
     * and is used to display the shopping cart in the wallet site.
     * @param $ShoppingCartXml
     * @return Output is the response from MasterCard services
     */
    public function postShoppingCartData($shoppingCartUrl, $shoppingCartXml) {
        $params = array(
// 				Connector::OAUTH_BODY_HASH => $this->generateBodyHash($shoppingCartXml)
        );
        $response = $this->doRequest($params, $shoppingCartUrl, self::POST, $shoppingCartXml);
        return $response;
    }

    public function postMerchantInitData($merchantInitUrl, $merchantInitXml) {
        $params = array(
            OAUTH_BODY_HASH => $this->generateBodyHash($merchantInitXml)
        );
        $response = $this->doRequest($params, $merchantInitUrl, self::POST, $merchantInitXml);
        return $response;
    }

    /**
     * SDK:
     * This method retrieves the payment and shipping information
     * for the current user/session.
     * @param unknown $accessToken
     * @param unknown $checkoutResourceUrl
     * @return Output is the Checkout XML string containing the users billing and shipping information
     */
    public function GetPaymentShippingResource($checkoutResourceUrl, $accessToken) {
        $params = array(
            self::OAUTH_TOKEN => $accessToken
        );

        $response = $this->doRequest($params, $checkoutResourceUrl, self::GET, null);
        return $response;
    }

    /**
     * This method submits the receipt transaction list to MasterCard as a final step
     * in the Wallet process.
     * @param $merchantTransactions
     * @return Output is the response from MasterCard services
     */
    public function PostCheckoutTransaction($postbackurl, $merchantTransactions) {
        $params = array(
            self::OAUTH_BODY_HASH => $this->generateBodyHash($merchantTransactions)
        );
     
            $response = $this->doRequest($params, $postbackurl, self::POST, $merchantTransactions);

            return $response;
        
    }

    public function getPreCheckoutData($preCheckoutUrl, $preCheckoutXml, $accessToken) {
        $params = array(
            self::OAUTH_TOKEN => $accessToken
        );
        $response = $this->doRequest($params, $preCheckoutUrl, self::POST, $preCheckoutXml);
        return $response;
    }

    public function getExpressCheckoutData($expressCheckoutUrl, $expressCheckoutXml, $accessToken) {
        $params = array(
            self::OAUTH_TOKEN => $accessToken
        );

        $response = $this->doRequest($params, $expressCheckoutUrl, self::POST, $expressCheckoutXml);
        return $response;
    }

    protected function OAuthParametersFactory() {

        $params = parent::OAuthParametersFactory();

        $params[self::ORIGIN_URL] = $this->originUrl;

        return $params;
    }

    /*     * ************* Private Methods **************************************************************************************************************************** */

    /**
     * SDK:
     * Get the user's request token and store it in the current user session.
     * @param $requestUrl
     * @param $callbackUrl
     * @return RequestTokenResponse
     */
    public function GetRequestToken($requestUrl, $callbackUrl) {

        $connector = Mage::getModel('masterpass/mpservice_connector');

        $params = array(
            $connector::OAUTH_CALLBACK => $callbackUrl
        );

        $response = $connector->doRequest($params, $requestUrl, $connector::POST, null);

        $requestTokenInfo = $this->parseConnectionResponse($response);

        $return = ''; //new RequestTokenResponse();
        $return->requestToken = isset($requestTokenInfo[self::OAUTH_TOKEN]) ? $requestTokenInfo[self::OAUTH_TOKEN] : '';
        $return->authorizeUrl = isset($requestTokenInfo[self::XOAUTH_REQUEST_AUTH_URL]) ? $requestTokenInfo[self::XOAUTH_REQUEST_AUTH_URL] : '';
        $return->callbackConfirmed = isset($requestTokenInfo[self::OAUTH_CALLBACK_CONFIRMED]) ? $requestTokenInfo[self::OAUTH_CALLBACK_CONFIRMED] : '';
        $return->oAuthExpiresIn = isset($requestTokenInfo[self::OAUTH_EXPIRES_IN]) ? $requestTokenInfo[self::OAUTH_EXPIRES_IN] : '';
        $return->oAuthSecret = isset($requestTokenInfo[self::OAUTH_TOKEN_SECRET]) ? $requestTokenInfo[self::OAUTH_TOKEN_SECRET] : '';



        $this->requestTokenInfo = $return;

        // Return the request token response class.
        return $return;
    }

    /**
     * SDK:
     * Assuming that all due diligence is done and assuming the presence of an established session,
     * successful reception of non-empty request token, and absence of any unanticipated
     * exceptions have been successfully verified, you are ready to go to the authorization
     * link hosted by MasterCard.
     * @param $acceptableCards
     * @param $checkoutProjectId
     * @param $xmlVersion
     * @param $shippingSuppression
     * @param $rewardsProgram
     * @param $authLevelBasic
     * @param $shippingLocationProfile
     * @param $walletSelector
     *
     * @return string - URL to redirect the user to the MasterPass wallet site
     */
    private function GetConsumerSignInUrl($acceptableCards, $checkoutProjectId, $xmlVersion, $shippingSuppression, $rewardsProgram, $authLevelBasic, $shippingLocationProfile, $walletSelector) {
        $baseAuthUrl = $this->requestTokenInfo->authorizeUrl;

        $xmlVersion = strtolower($xmlVersion);

        $connector = Mage::getModel('masterpass/mpservice_connector');
        // Use v1 if xmlVersion does not match correct patern
        if (!preg_match(self::XML_VERSION_REGEX, $xmlVersion)) {
            $xmlVersion = self::DEFAULT_XMLVERSION;
        }

        $token = $this->requestTokenInfo->requestToken;
        if ($token == null || $token == $connector::EMPTY_STRING) {
            throw new Exception($connector::EMPTY_REQUEST_TOKEN_ERROR_MESSAGE);
        }

        if ($baseAuthUrl == null || $baseAuthUrl == $connector::EMPTY_STRING) {
            throw new Exception($connector::INVALID_AUTH_URL);
        }

        // construct the Redirect URL
        $finalAuthUrl = $baseAuthUrl .
                $this->getParamString(self::ACCEPTABLE_CARDS, $acceptableCards, true) .
                $this->getParamString(self::CHECKOUT_IDENTIFIER, $checkoutProjectId) .
                $this->getParamString(self::OAUTH_TOKEN, $token) .
                $this->getParamString(self::VERSION, $xmlVersion);

        // If xmlVersion is v1 (default version), then shipping suppression, rewardsprogram and auth_level are not used
        if (strcasecmp($xmlVersion, self::DEFAULT_XMLVERSION) != $connector::V1) {

            if ($shippingSuppression == 'true') {
                $finalAuthUrl = $finalAuthUrl . $this->getParamString(self::SUPPRESS_SHIPPING_ADDRESS, $shippingSuppression);
            }

            if ((int) substr($xmlVersion, 1) >= 4 && $rewardsProgram == 'true') {
                $finalAuthUrl = $finalAuthUrl . $this->getParamString(self::ACCEPT_REWARDS_PROGRAM, $rewardsProgram);
            }

            if ($authLevelBasic) {
                $finalAuthUrl = $finalAuthUrl . $this->getParamString(self::AUTH_LEVEL, self::BASIC);
            }

            if ((int) substr($xmlVersion, 1) >= 4 && $shippingLocationProfile != null && !empty($shippingLocationProfile)) {
                $finalAuthUrl = $finalAuthUrl . $this->getParamString(self::SHIPPING_LOCATION_PROFILE, $shippingLocationProfile);
            }

            if ((int) substr($xmlVersion, 1) >= 5 && $walletSelector == 'true') {
                $finalAuthUrl = $finalAuthUrl . $this->getParamString(self::WALLET_SELECTOR, $walletSelector);
            }
        }
        return $finalAuthUrl;
    }

    /**
     * SDK:
     * Method to create the URL with GET Parameters
     *
     * @param $key
     * @param $value
     * @param $firstParam
     *
     * @return string
     */
    private function getParamString($key, $value, $firstParam = false) {
        $connector = Mage::getModel('masterpass/mpservice_connector');
        $paramString = $connector::EMPTY_STRING;

        if ($firstParam) {
            $paramString .= $connector::QUESTION;
        } else {
            $paramString .= $connector::AMP;
        }
        $paramString .= $key . $connector::EQUALS . $value;

        return $paramString;
    }

}

?>