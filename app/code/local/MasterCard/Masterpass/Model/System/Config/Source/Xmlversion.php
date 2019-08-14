<?php

/**
 * Used in creating options for Xml version config value selection
 *
 */
class MasterCard_Masterpass_Model_System_Config_Source_Xmlversion {
	
	/**
	 * Options getter
	 *
	 * @return array
	 */
	public function toOptionArray() {
		return array (
				array (
						'value' => "v6",
						'label' => Mage::helper ( 'masterpass' )->__ ( 'V6' ) 
                        )
	 
		);
	}
	
	/**
	 * Get options in "key-value" format
	 *
	 * @return array
	 */
	public function toArray() {
		return array (
				'v6' => Mage::helper ( 'masterpass' )->__ ( 'V6' )
				
		)
		;
	}
}
