<?php

/**
 * Used in creating options for User Flows config value selection
 *
 */
class MasterCard_Masterpass_Model_System_Config_Source_Userflows {

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray() {
        return array(
            array(
                'value' => '1',
                'label' => Mage::helper('masterpass')->__('Standard Checkout')
            ),
            array(
                'value' => '2',
                'label' => Mage::helper('masterpass')->__('Connected Checkout')
            ),
            array(
                'value' => '3',
                'label' => Mage::helper('masterpass')->__('Express Checkout')
            )
                )
        ;
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray() {
        return array(
            '1' => Mage::helper('masterpass')->__('Standard Checkout'),
            '2' => Mage::helper('masterpass')->__('Express Checkout')
                )
        ;
    }

}
