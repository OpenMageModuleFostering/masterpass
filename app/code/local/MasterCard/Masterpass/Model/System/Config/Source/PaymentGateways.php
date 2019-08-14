<?php


class MasterCard_Masterpass_Model_System_Config_Source_PaymentGateways
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        $request = Mage::app()->getRequest();
        // Show payment methods enabled on store view when viewed by the associated store view scope
        $storeCode = $request->getParam("store", null);

        if (!$storeCode) {
            $websiteCode = $request->getParam("website", null);

            if ($websiteCode) {
                $website = Mage::getModel("core/website")->load($websiteCode, "code");
                $storeCode = $website->getDefaultStore();
            }
        }

        /** @var Mage_Core_Model_Config_Element $paymentMethodConfigXml */
        $paymentMethodConfigXml = Mage::getSingleton("adminhtml/config")->getSection("payment")->groups;
        $methods = Mage::getSingleton("payment/config")->getActiveMethods($storeCode);

        // Initialise it with an empty value/label so the user can deselect all available methods if desired.
        // Doing so will put the extension in a disabled state.
        $options = array(array("value" => "", "label" => ""));
        foreach ($methods as $key => $method) {
            if (!($method instanceof Mage_Payment_Model_Method_Cc)) {
                continue;
            }

            if (!empty($paymentMethodConfigXml->{$key}->label)) {
                $label = (string) $paymentMethodConfigXml->{$key}->label;
            } else {
                $label = $method->getTitle();
            }
            $options[] = array(
                "value" => $key,
                "label" => $label,
            );
        }

        return $options;
    }
}
