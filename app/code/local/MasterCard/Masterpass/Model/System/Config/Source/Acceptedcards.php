<?php

/**
 * Used in creating options for MasterCard Masterpass Accepted Cards config value selection
 *
 */
class MasterCard_Masterpass_Model_System_Config_Source_Acceptedcards {
	
	/**
	 * Options getter
	 *
	 * @return array
	 */
	public function toOptionArray() {
		return array (
				array (
						'value' => "master",
						'label' => Mage::helper ( 'masterpass' )->__ ( 'Master Card' ) 
				),
				array (
						'value' => "amex",
						'label' => Mage::helper ( 'masterpass' )->__ ( 'American Express' ) 
				),
				array (
						'value' => "diners",
						'label' => Mage::helper ( 'masterpass' )->__ ( 'Diners' ) 
				),
				array (
						'value' => "discover",
						'label' => Mage::helper ( 'masterpass' )->__ ( 'Discover' ) 
				),
				array (
						'value' => "maestro",
						'label' => Mage::helper ( 'masterpass' )->__ ( 'Maestro' ) 
				),
				array (
						'value' => "visa",
						'label' => Mage::helper ( 'masterpass' )->__ ( 'Visa' ) 
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
		return array (
				'master' => Mage::helper ( 'masterpass' )->__ ( 'Master Card' ),
				'amex' => Mage::helper ( 'masterpass' )->__ ( 'American Express' ),
				'diners' => Mage::helper ( 'masterpass' )->__ ( 'Diners' ),
				'discover' => Mage::helper ( 'masterpass' )->__ ( 'Discover' ),
				'maestro' => Mage::helper ( 'masterpass' )->__ ( 'Maestro' ),
				'visa' => Mage::helper ( 'masterpass' )->__ ( 'Visa' ) 
		)
		;
	}
}
