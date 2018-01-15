<?php

namespace SilverCommerce\ShoppingCart\Extensions;

use SilverStripe\ORM\DataExtension;

/**
 * Overwrite group object so we can setup default groups
 * 
 * @author i-lateral (http://www.i-lateral.com)
 * @package shoppingcart
 */
class MemberExtension extends DataExtension
{
    /**
     * Find a shopping cart estimate from this
     * members contact
     *
     * @return void
     */
    public function getCart()
    {
        return $this
            ->owner
            ->getContact()
            ->Estimates()
            ->filter("ShoppingCart", 1)
            ->first();
    }
}
