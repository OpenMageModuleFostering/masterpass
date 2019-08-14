<?php

/**
 * Used in creating options for Yes|No config value selection
 *
 */
class MasterCard_Masterpass_Model_System_Config_Source_Transactionurl {
	
	/**
	 * Options getter
	 *
	 * @return array
	 */
	public function toOptionArray() {
		return array (
				array (
						'value' => 'https://sandbox.api.mastercard.com/online/v2/transaction',
						'label' => Mage::helper ( 'masterpass' )->__ ( 'Sandbox' ) 
				),
				array (
						'value' => '0',
						'label' => Mage::helper ( 'masterpass' )->__ ( '0' ) 
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
				'0' => Mage::helper ( 'masterpass' )->__ ( '0' ),
				'https://sandbox.api.mastercard.com/online/v2/transaction' => Mage::helper ( 'masterpass' )->__ ( 'Sandbox' ) 
		);
	}
}
