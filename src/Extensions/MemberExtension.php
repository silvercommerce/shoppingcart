<?php

namespace SilverCommerce\ShoppingCart\Extensions;

use SilverStripe\ORM\DataExtension;
use SilverCommerce\ShoppingCart\Model\ShoppingCart;

/**
 * Customise Member objects
 *
 */
class MemberExtension extends DataExtension
{
    /**
     * Get the currenty active shopping cart on a member
     *
     * @return ShoppingCart
     */
    public function getCart()
    {
        return $this
            ->getOwner()
            ->Contact()
            ->Estimates()
            ->find("ClassName", ShoppingCart::class);
    }

    /**
     * Update the current cart. Also make sure no more than one is
     * set at any one time.
     *
     * @return self
     */
    public function setCart(ShoppingCart $cart)
    {
        $curr = $this->getOwner()->getCart();
        $contact = $this->getOwner()->Contact();

        if (isset($curr) && $curr->ID != $cart->ID) {
            $curr->delete();
        }

        $cart->CustomerID = $contact->ID;

        return $this;
    }
}
