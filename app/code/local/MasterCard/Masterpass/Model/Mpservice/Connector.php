<?php

class MasterCard_Masterpass_Model_Mpservice_Connector extends Mage_Core_Model_Abstract {

    const AMP = "&";
    const QUESTION = "?";
    const EMPTY_STRING = "";
    const EQUALS = "=";
    const DOUBLE_QUOTE = '"';
    const COMMA = ',';
    const ENCODED_TILDE = '%7E';
    const TILDE = '~';
    const COLON = ':';
    const SPACE = ' ';
    const UTF_8 = 'UTF-8';
    const V1 = 'v1';
    const OAUTH_START_STRING = 'OAuth ';
    const REALM = 'realm';
    const ACCEPT = 'Accept';
    const CONTENT_TYPE = 'Content-Type';
    const SSL_CA_CER_PATH_LOCATION = '';
    const POST = "POST";
    const PUT = "PUT";
    const GET = "GET";
    const PKEY = 'pkey';
    const STRNATCMP = "strnatcmp";
    const SHA1 = "SHA1";
    const APPLICATION_XML = "application/xml";
    const AUTHORIZATION = "Authorization";
    const OAUTH_BODY_HASH = "oauth_body_hash";
    const BODY = "body";
    const MESSAGE = 'Message';
    // Signature Base String
    const OAUTH_SIGNATURE = "oauth_signature";
    const OAUTH_CONSUMER_KEY = 'oauth_consumer_key';
    const OAUTH_NONCE = 'oauth_nonce';
    const SIGNATURE_METHOD = 'oauth_signature_method';
    const OAUTH_TIMESTAMP = 'oauth_timestamp';
    const OAUTH_CALLBACK = "oauth_callback";
    const OAUTH_SIGNATURE_METHOD = 'oauth_signature_method';
    const OAUTH_VERSION = 'oauth_version';
    // Srings to detect errors in the service calls
    const ERRORS_TAG = "<Errors>";
    const HTML_TAG = "<html>";
    const HTML_BODY_OPEN = '<body>';
    const HTML_BODY_CLOSE = '</body>';
    // Error Messages
    const EMPTY_REQUEST_TOKEN_ERROR_MESSAGE = 'Invalid Request Token';
    const INVALID_AUTH_URL = 'Invalid Auth Url';
    const POSTBACK_ERROR_MESSAGE = 'Postback Transaction Call was unsuccessful';
    //Connection Strings
    const CONTENT_TYPE_APPLICATION_XML = 'Content-Type: application/xml';
    const SSL_ERROR_MESSAGE = "SSL Error Code: %s %sSSL Error Message: %s";

    public $signatureBaseString;
    public $authHeader;
    protected $consumerKey;
    private $privateKey;
    public $keystorePath;
    public $keystorePassword;
    private $version = '1.0';
    private $signatureMethod = 'RSA-SHA1';
    public $realm = "eWallet"; // This value is static

    /**
     * Constructor for Connector
     * @param string $consumerKey
     * @param string $privateKey
     */

    public function __construct() {
        $this->consumerKey = $this->getConsumerKey();
        $this->privateKey = $this->getPrivateKey();
    }

    private function getPrivateKey() {
        $keystore = $this->merchantPrivateKey();
        if ($keystore['pkey']) {
            return $keystore['pkey'];
        } else {
            Mage::log('Invalid private key password');
            return null;
        }
    }
    public function merchantPrivateKey() {
        if (Mage::getStoreConfig('masterpass/config/environment') === 'sandbox') {
            $thispath = Mage::getBaseDir() . "/shell/certs/" . Mage::getStoreConfig('masterpass/config/sbkeystorepath');
            $keystorePassword = Mage::helper('core')->decrypt(Mage::getStoreConfig('masterpass/config/sbkeystorepassword'));
        } else {
            $thispath = Mage::getBaseDir() . "/shell/certs/" . Mage::getStoreConfig('masterpass/config/prkeystorepath');
            $keystorePassword = Mage::helper('core')->decrypt(Mage::getStoreConfig('masterpass/config/prkeystorepassword'));
        }
        try {
            if (strstr($keystorePassword, '.sh')) {
                $key_pw = exec('sh ' . escapeshellarg($keystorePassword));
            } elseif (strstr($keystorePassword, '.bat')) {
                $key_pw = exec(escapeshellarg($keystorePassword));
            }
            $keystore = array();

            $path = realpath($thispath);
            //error_log("PATH IS " . $path);
            $pkcs12 = file_get_contents($path);
            trim(openssl_pkcs12_read($pkcs12, $keystore, $key_pw));
            //error_log("GOT TO HERE - " . $keystore['pkey']);
        } catch (Exception $e) {
            Mage::log('Invalid private key password');
        }
        return $keystore;
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
     * Method to convert strings 'true' and 'false' to a boolean value
     * If parameter string is not 'true' (case insensitive), then false will be returned
     *
     * @param String : $str
     *
     * @return boolean
     */
    public static function str_to_bool($str) {
        return (strcasecmp($str, TRUE) == 0) ? true : false;
    }

    public static function formatXML($resources) {

        if ($resources != null) {
           $resources = Mage::helper('masterpass')->formatXML($resources);
        }
        return $resources;
    }

    protected function doSimpleRequest($url, $requestMethod, $body = null) {
        return self::doRequest(array(), $url, $requestMethod, $body);
    }

    /*     * ************* Private Methods **************************************************************************************************************************** */

    /**
     *  Method used for all Http connections
     *
     * @param $params
     * @param $url
     * @param $requestMethod
     * @param $body
     *
     * @throws Exception - When connection error
     *
     * @return mixed - Raw data returned from the HTTP connection
     */
    public function doRequest($params, $url, $requestMethod, $body = null) {

        if ($body != null) {
            $params[self::OAUTH_BODY_HASH] = $this->generateBodyHash($body);
        }

        try {
            return $this->connect($params, $this->realm, $url, $requestMethod, $body);
        } catch (Exception $e) {
            throw $this->checkForErrors($e);
        }
    }

    /**
     * SDK:
     * Method to generate the body hash
     * 
     * @param $body
     * 
     * @return string
     */
    protected function generateBodyHash($body) {
        $sha1Hash = sha1($body, true);
        return base64_encode($sha1Hash);
    }

    /**
     * This method generates and returns a unique nonce value to be used in
     * 	Wallet API OAuth calls.
     *
     * @param $length
     * 
     * @return string
     */
    private function generateNonce($length) {
        if (function_exists('com_create_guid') === true) {
            return trim(com_create_guid(), '{}');
        } else {
            $u = md5(uniqid('nonce_', true));
            return substr($u, 0, $length);
        }
    }

    /**
     * Builds a Auth Header used in connection to MasterPass services
     * 
     * @param $params
     * @param $realm
     * @param $url
     * @param $requestMethod
     * @param $body
     * 
     * @return string - Auth header
     */
    private function buildAuthHeaderString($params, $realm, $url, $requestMethod, $body) {

        $params = array_merge($this->OAuthParametersFactory(), $params);

        $signature = $this->generateAndSignSignature($params, $url, $requestMethod, $this->privateKey, $body);
        $params[self::OAUTH_SIGNATURE] = $signature;

        $startString = self::OAUTH_START_STRING;
        if (!empty($realm)) {
            $startString = $startString . self::REALM . self::EQUALS . self::DOUBLE_QUOTE . $realm . self::DOUBLE_QUOTE . self::COMMA;
        }

        foreach ($params as $key => $value) {
            $startString = $startString . $key . self::EQUALS . self::DOUBLE_QUOTE . $this->RFC3986urlencode($value) . self::DOUBLE_QUOTE . self::COMMA;
        }

        $this->authHeader = substr($startString, 0, strlen($startString) - 1);

        return $this->authHeader;
    }

    /**
     * Method to generate base string and generate the signature
     *  
     * @param $params
     * @param $url
     * @param $requestMethod
     * @param $privateKey
     * @param $body
     * 
     * @return string
     */
    private function generateAndSignSignature($params, $url, $requestMethod, $privateKey, $body) {
        $baseString = $this->generateBaseString($params, $url, $requestMethod);
        $this->signatureBaseString = $baseString;

        $signature = $this->sign($baseString, $privateKey);

        return $signature;
    }

    /**
     * Method to sign string
     * 
     * @param $string
     * @param $privateKey
     * 
     * @return string
     */
    private function sign($string, $privateKey) {
        $privatekeyid = openssl_get_privatekey($privateKey);
        openssl_sign($string, $signature, $privatekeyid, OPENSSL_ALGO_SHA1);

        return base64_encode($signature);
    }

    /**
     * Method to generate the signature base string 
     * 
     * @param $params
     * @param $url
     * @param $requestMethod
     * 
     * @return string
     */
    private function generateBaseString($params, $url, $requestMethod) {
        $urlMap = parse_url($url);

        $url = $this->formatUrl($url, $params);

        $params = $this->parseUrlParameters($urlMap, $params);


        $baseString = strtoupper($requestMethod) . self::AMP . $this->RFC3986urlencode($url) . self::AMP;
        ksort($params);

        $parameters = self::EMPTY_STRING;
        foreach ($params as $key => $value) {
            $parameters = $parameters . $key . self::EQUALS . $this->RFC3986urlencode($value) . self::AMP;
        }
        $parameters = $this->RFC3986urlencode(substr($parameters, 0, strlen($parameters) - 1));
        return $baseString . $parameters;
    }

    /**
     * Method to extract the URL parameters and add them to the params array
     * 
     * @param string $urlMap
     * @param string $params
     * 
     * @return string|multitype:
     */
    function parseUrlParameters($urlMap, $params) {
        if (empty($urlMap['query'])) {
            return $params;
        } else {
            $str = $urlMap['query'];
            parse_str($str, $urlParamsArray);
            foreach ($urlParamsArray as $key => $value) {
                $urlParamsArray[$key] = $this->RFC3986urlencode($value);
            }
            return array_merge($params, $urlParamsArray);
        }
    }

    /**
     * Method to format the URL that is included in the signature base string 
     * 
     * @param string $url
     * @param string $params
     * 
     * @return string|string
     */
    function formatUrl($url, $params) {
        if (!parse_url($url)) {
            return $url;
        }
        $urlMap = parse_url($url);
        return $urlMap['scheme'] . '://' . $urlMap['host'] . $urlMap['path'];
    }

    /**
     * URLEncoder that conforms to the RFC3986 spec.
     * PHP's internal function, rawurlencode, does not conform to RFC3986 for PHP 5.2
     * 
     * @param unknown $string
     * 
     * @return unknown|mixed
     */
    function RFC3986urlencode($string) {
        if ($string === false) {
            return $string;
        } else {
            return str_replace(self::ENCODED_TILDE, self::TILDE, rawurlencode($string));
        }
    }

    /**
     * Method to create all default parameters used in the base string and auth header
     * 
     * @return array
     */
    protected function OAuthParametersFactory() {
        $nonce = $this->generateNonce(16);
        $time = time();

        $params = array(
            self::OAUTH_CONSUMER_KEY => $this->consumerKey,
            self::OAUTH_SIGNATURE_METHOD => $this->signatureMethod,
            self::OAUTH_NONCE => $nonce,
            self::OAUTH_TIMESTAMP => $time,
            self::OAUTH_VERSION => $this->version
        );

        return $params;
    }

    /**
     * General method to handle all HTTP connections
     * 
     * @param unknown $params
     * @param unknown $realm
     * @param unknown $url
     * @param unknown $requestMethod
     * @param string $body
     * 
     * @throws Exception - If connection fails or receives a HTTP status code > 300
     * 
     * @return mixed
     */
    private function connect($params, $realm, $url, $requestMethod, $body = null) {
        $curl = curl_init($url);

        // Adds the CA cert bundle to authenticate the SSL cert
        //curl_setopt($curl, CURLOPT_CAINFO,  __DIR__ .self::SSL_CA_CER_PATH_LOCATION);  

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE); // This should always be TRUE to secure SSL connections

        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            self::ACCEPT . self::COLON . self::SPACE . self::APPLICATION_XML,
            self::CONTENT_TYPE . self::COLON . self::SPACE . self::APPLICATION_XML . ";charset=\"UTF-8\"",
            self::AUTHORIZATION . self::COLON . self::SPACE . $this->buildAuthHeaderString($params, $realm, $url, $requestMethod, $body)
        ));

        if ($requestMethod == self::GET) {
            curl_setopt($curl, CURLOPT_HTTPGET, TRUE);
        } else {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, strtoupper($requestMethod));
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        }

        $result = curl_exec($curl);

        // Check if any error occurred
        if (curl_errno($curl)) {
            throw new Exception(sprintf(self::SSL_ERROR_MESSAGE, curl_errno($curl), PHP_EOL, curl_error($curl)), curl_errno($curl));
        }


        // Check for errors and throw an exception
        if (($errorCode = curl_getinfo($curl, CURLINFO_HTTP_CODE)) > 300) {
            throw new Exception($result, $errorCode);
        }

        return $result;
    }

    /**
     * Method to check for HTML content in the exception message and remove everything except the body
     * 
     * @param Exception $e
     * 
     * @return Exception
     */
    private function checkForErrors(Exception $e) {
        if (strpos($e->getMessage(), self::HTML_TAG) !== false) {
            $body = substr($e->getMessage(), strpos($e->getMessage(), self::HTML_BODY_OPEN) + 6, strpos($e->getMessage(), self::HTML_BODY_CLOSE));
            return new Exception($body);
        } else {
            return $e;
        }
    }

}
