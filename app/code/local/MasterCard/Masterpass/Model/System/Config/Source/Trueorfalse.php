<?php

/**
 * Used in creating options for Shipping suppression config value selection
 *
 */
class MasterCard_Masterpass_Model_System_Config_Source_Trueorfalse {
	
	/**
	 * Options getter
	 *
	 * @return array
	 */
	public function toOptionArray() {
		return array (
				array (
						'value' => "true",
						'label' => Mage::helper ( 'masterpass' )->__ ( 'true' ) 
				),
				array (
						'value' => "false",
						'label' => Mage::helper ( 'masterpass' )->__ ( 'false' ) 
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
				'true' => Mage::helper ( 'masterpass' )->__ ( 'true' ),
				'false' => Mage::helper ( 'masterpass' )->__ ( 'false' ) 
		)
		;
	}
}
