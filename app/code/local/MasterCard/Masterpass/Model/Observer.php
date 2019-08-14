<?php

/*
 * Developer: Pavith Lovan
 */
/*
 * To change this template, choose Tools | Templates and open the template in the editor.
 */

class MasterCard_Masterpass_Model_Observer {

    const XML_PATH_EMAIL_RECIPIENT = 'contacts/email/recipient_email';
    const XML_PATH_EMAIL_SENDER = 'contacts/email/sender_email_identity';
    const XML_PATH_EMAIL_TEMPLATE = 'masterpass_email_template';
    const XML_SEVERITY_ICONS_URL_PATH = 'system/adminnotification/severity_icons_url';

    protected $_severityIconsUrl;
    public function mastterPassToControllerActionPostDispatch(Varien_Event_Observer $observer) {
        if (!Mage::helper('core')->isModuleOutputEnabled('MasterCard_Masterpass')) {
            return $this;
        }
        $controller = $observer->getEvent()->getControllerAction();

        if (Mage::helper("masterpass")->checkIfSensitiveRequest($controller->getRequest())) {
            Mage::helper('masterpass')->resetMasterData();
        }
    }
    // check if private key is set
    public function checkPrivateKey($observer) {
        
        $controller = $observer->getEvent()->getControllerAction();

        $mp = $controller->getRequest()->getParams();

        $cache = Mage::app()->getCache();
        
        if(!Mage::helper('core')->isModuleOutputEnabled('MasterCard_Masterpass')){
            return $this;
        }
        
        if (Mage::getSingleton('admin/session')->isLoggedIn()) {

            //create log when masterpass configuraution is changed
            $fields = Mage::app()->getRequest()->getParams();
            if (isset($fields['section']) && $fields['section'] == 'masterpass') {
                if (isset($fields['form_key'])) {
                    $admin = Mage::getSingleton('admin/session')->getUser();

                    error_log('MasterPass Configuration changed by: ' . $admin->getUsername() . ' on ' . date("F j, Y, g:i a"), 0);
                }
            }


            if (Mage::getStoreConfig('masterpass/config/enabled')) {
                if ($cache->load("masterpass_pk") === false) {
                    if (!Mage::helper('masterpass')->checkPrivateKey()) {
                        $this->getPkeyMessage($mp);
                        $this->sendNotification();
                    }
                    $cache->save('true', 'masterpass_pk', array("masterpass_pk"), $lifeTime = 2);
                }
            }
        }
    }

    public function getPkeyMessage($mp) {

        $message = 'Important: Please enter the path location of the private key executable';
        if ($mp['section'] != 'masterpass') {
            $message .= '<script type="text/javascript">
//<![CDATA[
    var messagePopupClosed = false;
    function openMessagePopup() {
        var height = $("html-body").getHeight();
        $("message-popup-window-mask").setStyle({"height":height+"px"});
        toggleSelectsUnderBlock($("message-popup-window-mask"), false);
        Element.show("message-popup-window-mask");
        $("message-popup-window").addClassName("show");
    }

    function closeMessagePopup() {
        toggleSelectsUnderBlock($("message-popup-window-mask"), true);
        Element.hide("message-popup-window-mask");
        $("message-popup-window").removeClassName("show");
        messagePopupClosed = true;
    }

    Event.observe(window, "load", openMessagePopup);
    Event.observe(window, "keyup", function(evt) {
        if(messagePopupClosed) return;
        var code;
        if (evt.keyCode) code = evt.keyCode;
        else if (evt.which) code = evt.which;
        if (code == Event.KEY_ESC) {
            closeMessagePopup();
        }
    });
//]]>
</script>';
            $message .= '<div id="message-popup-window-mask" style="display:none;"></div>
<div id="message-popup-window" class="message-popup">
    <div class="message-popup-head">
        <a href="#" onclick="closeMessagePopup(); return false;" title="close"><span>close</span></a>
        <h2>MasterPass Private Key Executable Path</h2>
    </div>
    <div class="message-popup-content">
        <div class="message">
            <span class="message-icon message-notice" style="background-image:url(' . "'" . $this->getSeverityIconsUrl() . "'" . '">Notice</span>
            <p class="message-text">Important: Please enter the path location of the private key executable</p>
        </div>
        <p class="read-more"><a href="' . Mage::helper("adminhtml")->getUrl("/system_config/edit/section/masterpass/") . '">Click here to enter private key executable path</a></p>
    </div>
</div>';
        }
        Mage::getSingleton('adminhtml/session')->addNotice($message);
    }

    public function getSeverityIconsUrl() {
        if (is_null($this->_severityIconsUrl)) {
            $this->_severityIconsUrl = (Mage::app()->getFrontController()->getRequest()->isSecure() ? 'https://' : 'http://')
                    . sprintf(Mage::getStoreConfig(self::XML_SEVERITY_ICONS_URL_PATH), Mage::getVersion(), 'SEVERITY_NOTICE')
            ;
        }
        return $this->_severityIconsUrl;
    }

// check private key password
    // if private key path file is not set, send email notification to notify merchant
    public function noPassKeyNotification() {
        // check if masterpass is enabled
        if (Mage::getStoreConfig('masterpass/config/enabled')) {
            if (Mage::getStoreConfig('masterpass/config/sbkeystorepassword') == null && Mage::getStoreConfig('masterpass/config/environment') == 'sandbox') {
                $this->sendNotification();
            } elseif (Mage::getStoreConfig('masterpass/config/prkeystorepassword') == null && Mage::getStoreConfig('masterpass/config/environment') == 'production') {
                $this->sendNotification();
            } else {
                return;
            }
        }
    }

    public function sendNotification() {

        if (Mage::getStoreConfig('masterpass/config/notification_email') != null) {

            $subject = 'MasterPass Private Key needed';
            $message = '<div>
<p>Alert: The path for the private key executable needs to be correctly configured in the Administration Console. 
<br/>The Private Key Executable Path is either empty or the value is incorrect.  Please log into the Magento MasterPass   
<br/>administration console and enter the correct path. Make certain the Private Key Executable Path is populated and  
<br/>contains the correct path to the executable that retrieves the private key.
</p>
</div>';
            $to = Mage::getStoreConfig('masterpass/config/notification_email');
            $type = 'html'; // or HTML
            $charset = 'utf-8';

            $mail = Mage::getStoreConfig('trans_email/ident_general/email');
            $uniqid = md5(uniqid(time()));
            $headers = 'From: ' . $mail . "\n";
            $headers .= 'Reply-to: ' . $mail . "\n";
            $headers .= 'Return-Path: ' . $mail . "\n";
            $headers .= 'Message-ID: <' . $uniqid . '@' . $_SERVER['SERVER_NAME'] . ">\n";
            $headers .= 'MIME-Version: 1.0' . "\n";
            $headers .= 'Date: ' . gmdate('D, d M Y H:i:s', time()) . "\n";
            $headers .= 'X-Priority: 3' . "\n";
            $headers .= 'X-MSMail-Priority: Normal' . "\n";
            $headers .= 'Content-Type: multipart/mixed;boundary="----------' . $uniqid . '"' . "\n\n";
            $headers .= '------------' . $uniqid . "\n";
            $headers .= 'Content-type: text/' . $type . ';charset=' . $charset . '' . "\n";
            $headers .= 'Content-transfer-encoding: 7bit';
            try {
                mail($to, $subject, $message, $headers);
            } catch (Exception $e) {
                return;
            }
        }
        return;
    }

}

?>
