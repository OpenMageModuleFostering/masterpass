<?php

/**
 * Used in creating options for Button size config value selection
 *
 */
class MasterCard_Masterpass_Model_System_Config_Source_Buttonsize {
	
	/**
	 * Options getter
	 *
	 * @return array
	 */
	public function toOptionArray() {
		return array (
				array (
						'value' => '147x034px',
						'label' => Mage::helper ( 'masterpass' )->__ ( '147x034px' ) 
				),
				array (
						'value' => '160x037px',
						'label' => Mage::helper ( 'masterpass' )->__ ( '160x037px' ) 
				),
				array (
						'value' => '166x038px',
						'label' => Mage::helper ( 'masterpass' )->__ ( '166x038px' ) 
				),
				array (
						'value' => '180x042px',
						'label' => Mage::helper ( 'masterpass' )->__ ( '180x042px' ) 
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
				
				'147x034px' => Mage::helper ( 'masterpass' )->__ ( '147x034px' ),
				'160x037px' => Mage::helper ( 'masterpass' )->__ ( '160x037px' ),
				'166x038px' => Mage::helper ( 'masterpass' )->__ ( '166x038px' ),
				'180x042px' => Mage::helper ( 'masterpass' )->__ ( '180x042px' ) 
		)
		;
	}
}
