<?php

/*
  Pavith Lovan
 */

class MasterCard_Masterpass_Block_Customer_Link extends Mage_Core_Block_Template {

    public function addProfileLink() {
        $private_key = Mage::helper('masterpass')->checkPrivateKey();
        if ($private_key) {
            if (Mage::helper('masterpass')->isMasterpassEnabled() && Mage::helper('masterpass')->isExpressEnabled()) {
                $navigation = $this->getParentBlock();

                $navigation->addLink('masterpass', 'masterpass/masterpasspairing', $this->__("Connect With MasterPass"), array('_secure' => true));
            }
        }
    }

}
