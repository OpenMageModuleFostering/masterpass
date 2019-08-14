<?php

/**
 * Used in creating options for Button size config value selection
 *
 */
class MasterCard_Masterpass_Model_System_Config_Source_Acceptancemarksize {
	
	/**
	 * Options getter
	 *
	 * @return array
         * /mp_mc_acc_023px_gif.gif
	 */
	public function toOptionArray() {
		return array (
				array (
						'value' => '023px',
						'label' => Mage::helper ( 'masterpass' )->__ ( '023px' ) 
				),
				array (
						'value' => '030px',
						'label' => Mage::helper ( 'masterpass' )->__ ( '030px' ) 
				),
				array (
						'value' => '034px',
						'label' => Mage::helper ( 'masterpass' )->__ ( '034px' ) 
				),
				array (
						'value' => '038px',
						'label' => Mage::helper ( 'masterpass' )->__ ( '038px' ) 
				),
                    array (
						'value' => '050px',
						'label' => Mage::helper ( 'masterpass' )->__ ( '050px' ) 
				) ,
                    array (
						'value' => '065px',
						'label' => Mage::helper ( 'masterpass' )->__ ( '065px' ) 
				) ,
                    array (
						'value' => '113px',
						'label' => Mage::helper ( 'masterpass' )->__ ( '113px' ) 
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
				
				'023px' => Mage::helper ( 'masterpass' )->__ ( '023px' ),
				'030px' => Mage::helper ( 'masterpass' )->__ ( '030px' ),
				'034px' => Mage::helper ( 'masterpass' )->__ ( '034px' ),
				'038px' => Mage::helper ( 'masterpass' )->__ ( '038px' ) ,
                                '050px' => Mage::helper ( 'masterpass' )->__ ( '050px' ),
				'065px' => Mage::helper ( 'masterpass' )->__ ( '065px' ),
				'113px' => Mage::helper ( 'masterpass' )->__ ( '113px' ) 
		)
		;
	}
}
