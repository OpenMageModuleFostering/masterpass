<?php

/**
 * Used in creating options for Yes|No config value selection
 *
 */
class MasterCard_Masterpass_Model_System_Config_Source_Environment {
	
	/**
	 * Options getter
	 *
	 * @return array
	 */
	public function toOptionArray() {
		return array (
				array (
						'value' => "sandbox",
						'label' => Mage::helper ( 'masterpass' )->__ ( 'Sandbox' ) 
				),
				array (
						'value' => "production",
						'label' => Mage::helper ( 'masterpass' )->__ ( 'Production' ) 
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
				'sandbox' => Mage::helper ( 'masterpass' )->__ ( 'Sandbox' ),
				'production' => Mage::helper ( 'masterpass' )->__ ( 'Production' ) 
		)
		;
	}
}
