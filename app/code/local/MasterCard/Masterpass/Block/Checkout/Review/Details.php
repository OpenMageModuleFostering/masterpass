<?php


class MasterCard_Masterpass_Block_Checkout_Review_Details extends Mage_Checkout_Block_Cart_Totals
{
    protected $_address = null;

    /**
     * Get shipping address from quote.
     *
     * @return Mage_Sales_Model_Order_Address
     */
    public function getAddress()
    {
        if ($this->_address === null) {
            $this->_address = $this->getQuote()->getShippingAddress();
        }
        return $this->_address;
    }

    /**
     * Return review quote totals.
     *
     * @return array
     */
    public function getTotals()
    {
        return $this->getQuote()->getTotals();
    }
}
