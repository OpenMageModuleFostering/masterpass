<?php

/*
  // Pavith Lovan
 */

class MasterCard_Masterpass_Block_Customer_Wrapper extends Mage_Core_Block_Template {

    public function getPairingButton() {
        if (Mage::getStoreConfig('masterpass/config/enabled')) {
            return true;
        } else {
            return false;
        }
    }

    public function pairingcallbackUrl() {
        return Mage::getUrl('', array('_secure' => true)) . "masterpass/masterpasspairing/pairingcallback";
    }

    public function isWalletPaired() {
        return Mage::helper('masterpass')->checkWallet();
    }

    public function pairingData() {
        $pairingData = Mage::helper('masterpass')->pairingDataTypes();
        $pairingData = explode(',', $pairingData);
        return $pairingData;
    }

}
